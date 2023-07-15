<?php

namespace app\jobs;

use app\models\StockQuotationMinutes;
use app\models\Stocks;
use Nacmartin\PhpExecJs\PhpExecJs;
use Yii;
use yii\base\ErrorException;

class GrabDailyJob extends \yii\base\BaseObject implements \yii\queue\JobInterface
{

    /**
     * @var string
     */
    public ?string $date;

    /**
     * @var string|null
     */
    public ?string $code;

    public string $apiUrl = 'http://finance.sina.com.cn/realstock/company/%s/hisdata/klc_cm.js?day=%s';

    /**
     * @inheritDoc
     */
    public function execute($queue)
    {
        $sdkJs = Yii::$app->basePath . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'sina' . DIRECTORY_SEPARATOR . 'sf_sdk.js';
        $execjs = new PhpExecJs();
        $execjs->createContextFromFile($sdkJs);

        $date = $this->date;
        if (!$date) {
            $date = date('Y-m-d');
        }

        $code = $this->code;
        if ($code) {
            $codes = explode(',', $code);
        } else {
            $codes = $this->getCodes();
        }

        $lastCode = '';
        foreach ($codes as $code) {
            $lastCode = substr($code, 2);

            $url = sprintf($this->apiUrl, $code, $date);

            try {
                $data = file_get_contents($url);

                $patten = sprintf('/KLC_ML_%s="(.+?)"/', $code);
                preg_match($patten, $data, $match);

                if (!isset($match[1])) {
                    throw new ErrorException($code . '没有数据！');
                }
            } catch (ErrorException $e) {
                echo $e->getMessage() . PHP_EOL;

                continue;
            }
        }

        print_r($match);
        die();

        $command = StockQuotationMinutes::getDb()->createCommand();

        $data = explode(',', $match[1]);
        foreach ($data as $datum) {
            $minutes = $execjs->evalJs('decode("' . $datum . '")');
            $stockDaily = [];
            foreach ($minutes as $minute) {
                $dailyDate = $minute['date'] ?? null;
                $price = $minute['price'] ?: 0.00;

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

        Yii::$app->queue->push(new GrabDailyJob([
            'date' => $this->date,
            'code' => $stock['code'],
        ]));
    }

    /**
     * @param string|null $code
     * @return string[]
     */
    public function getCodes(?string $code = null)
    {
        $stocks = Stocks::find()->where(['>', 'code', $this->code ?? '0'])->orderBy(['code' => SORT_ASC])->limit(10)->asArray()->all();

        return array_map(function ($stock) {
            return $stock['exchange'] . $stock['code'];
        }, $stocks);
    }
}