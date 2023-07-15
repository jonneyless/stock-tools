<?php

namespace app\jobs;

use app\models\StockQuotationMinutes;
use app\models\Stocks;
use Nacmartin\PhpExecJs\PhpExecJs;
use Yii;
use yii\base\ErrorException;

class GrabMinutesJob extends \yii\base\BaseObject implements \yii\queue\JobInterface
{

    /**
     * @var string
     */
    public string $date;

    /**
     * @var string|null
     */
    public ?string $code;

    /**
     * @inheritDoc
     */
    public function execute($queue)
    {
        $sdkJs = Yii::$app->basePath . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'sina' . DIRECTORY_SEPARATOR . 'sf_sdk.js';
        $execjs = new PhpExecJs();
        $execjs->createContextFromFile($sdkJs);

        $year = substr($this->date, 0, 4);
        $month = substr($this->date, 4, 2);

        $stock = Stocks::find()->where(['>', 'code', $this->code ?? '0'])->andWhere(['status' => 1])->orderBy(['code' => SORT_ASC])->one();
        if (!$stock) {
            $month++;
            if ($month > 12) {
                $year++;
                $month = 1;
            }

            $this->date = sprintf('%d%02d', $year, $month);

            if ($this->date > date('Ym')) {
                echo '处理结束！' . PHP_EOL;
                die();
            }

            $stock = Stocks::find()->where(['>', 'code', '0'])->andWhere(['status' => 1])->orderBy(['code' => SORT_ASC])->one();
        }

        echo '开始处理：' . $this->date . ' - ' . $stock['code'] . PHP_EOL;

        $code = $stock['exchange'] . $stock['code'];
        $url = sprintf('http://finance.sina.com.cn/realstock/company/%s/hisdata/%s/%s.js?d=%s', $code, $year, $month, $this->date);

        try {
            $data = file_get_contents($url);

            $patten = sprintf('/MLC_%s_%s_%s="(.+?)"/', $code, $year, $month);
            preg_match($patten, $data, $match);

            if (!isset($match[1])) {
                throw new ErrorException($stock['code'] . '没有数据！');
            }
        } catch (ErrorException $e) {
            echo $e->getMessage() . PHP_EOL;

            Stocks::updateAll(['status' => 0], ['code' => $stock['code']]);

            Yii::$app->queue->push(new GrabMinutesJob([
                'date' => $this->date,
                'code' => $stock['code'],
            ]));

            return;
        }

        $command = StockQuotationMinutes::getDb()->createCommand();

        $data = explode(',', $match[1]);
        foreach ($data as $datum) {
            $minutes = $execjs->evalJs('decode("' . $datum . '")');
            $stockDaily = [];
            foreach ($minutes as $minute) {
                $dailyDate = $minute['date'] ?? null;
                $price = $minute['price'] ? : 0.00;

                if ($dailyDate) {
                    if ($stockDaily) {
                        $command->addInsert($stockDaily);
                    }

                    $stockDaily = [
                        'code' => $stock['code'],
                        'date' => date('Ymd', strtotime($dailyDate)),
                        'opening_price' => $price,
                        'high_price' => $price,
                        'low_price' => $price,
                        'minutes' => [],
                    ];
                }

                $stockDaily['closing_price'] = $price;
                $stockDaily['high_price'] = max($stockDaily['high_price'], $price);
                $stockDaily['low_price'] = min($stockDaily['low_price'], $price);
                $stockDaily['minutes'][] = [
                    'volume' => $minute['volume'] ?? 0,
                    'price' => $price,
                    'avg_price' => $minute['avg_price'] ?? 0.00,
                ];
            }

            if ($stockDaily) {
                $command->addInsert($stockDaily);
            }
        }

        $command->executeBatch(StockQuotationMinutes::collectionName());

        Yii::$app->queue->push(new GrabMinutesJob([
            'date' => $this->date,
            'code' => $stock['code'],
        ]));
    }
}