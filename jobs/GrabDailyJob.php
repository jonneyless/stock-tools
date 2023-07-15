<?php

namespace app\jobs;

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

    /**
     * @var int
     */
    public int $manual = 0;

    /**
     * @var string
     */
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

        if (!$codes) {
            echo '处理完毕' . PHP_EOL;
            return;
        }

        $lastCode = '';
        $dailyMinutes = [];
        foreach ($codes as $code) {
            $lastCode = substr($code, 2);

            echo '开始处理：' . $date . ' - ' . $lastCode . PHP_EOL;

            $url = sprintf($this->apiUrl, $code, $date);

            try {
                $jsData = file_get_contents($url);

                $patten = sprintf('/KLC_ML_%s="([^"]*)"/', $code);
                preg_match($patten, $jsData, $match);

                if (!isset($match[1])) {
                    throw new ErrorException($code . '没有数据！');
                }

                if (!$match[1]) {
                    continue;
                }

                $data = array_slice(explode(',', $match[1]), -1, 1);
                $dailyMinutes[$lastCode] = $execjs->evalJs('decode("' . $data[0] . '")');
            } catch (ErrorException $e) {
                echo $e->getMessage() . PHP_EOL;

                Stocks::updateAll(['status' => 0], ['code' => $lastCode]);

                continue;
            } catch (\RuntimeException $e) {
                echo $e->getMessage() . PHP_EOL;
                var_dump($match[1]) . PHP_EOL;
                return;
            }
        }

//        $command = StockQuotationMinutes::getDb()->createCommand();
//        foreach ($dailyMinutes as $code => $minutes) {
//            $stockDaily = [];
//            foreach ($minutes as $minute) {
//                $dailyDate = $minute['date'] ?? null;
//                $price = $minute['price'] ?: 0.00;
//
//                if ($dailyDate) {
//                    $stockDaily = [
//                        'code' => $code,
//                        'date' => date('Ymd', strtotime($dailyDate)),
//                        'opening_price' => $price,
//                        'high_price' => $price,
//                        'low_price' => $price,
//                        'minutes' => [],
//                    ];
//                }
//
//                $stockDaily['closing_price'] = $price;
//                $stockDaily['high_price'] = max($stockDaily['high_price'], $price);
//                $stockDaily['low_price'] = min($stockDaily['low_price'], $price);
//                $stockDaily['minutes'][] = [
//                    'volume' => $minute['volume'] ?? 0,
//                    'price' => $price,
//                    'avg_price' => $minute['avg_price'] ?? 0.00,
//                ];
//            }
//
//            if ($stockDaily) {
//                $command->addInsert($stockDaily);
//            }
//        }
//
//        $command->executeBatch(StockQuotationMinutes::collectionName());

        if ($this->manual != 1 && $lastCode) {
            $codes = $this->getCodes($lastCode);

            Yii::$app->queue->push(new GrabDailyJob([
                'date' => $date,
                'code' => join(',', $codes),
                'manual' => $this->manual,
            ]));
        }
    }

    /**
     * @param string|null $code
     * @return string[]
     */
    public function getCodes(?string $code = null)
    {
        $stocks = Stocks::find()->where(['>', 'code', $code ?? '0'])->andWhere(['status' => 1])->orderBy(['code' => SORT_ASC])->limit(10)->asArray()->all();

        if (!$stocks) {
            return [];
        }

        return array_map(function ($stock) {
            return $stock['exchange'] . $stock['code'];
        }, $stocks);
    }
}