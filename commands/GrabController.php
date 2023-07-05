<?php

namespace app\commands;

use app\models\StockQuotationMinutes;
use app\models\Stocks;
use Nacmartin\PhpExecJs\PhpExecJs;
use yii\base\ErrorException;
use yii\console\Controller;
use Yii;

/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class GrabController extends Controller
{

    private $apiUrl = 'http://finance.sina.com.cn/realstock/company/%s/hisdata/%s/%s.js?d=%s';

    public function actionCodes()
    {
        $url = 'https://quote.stockstar.com/stock/stock_index.htm';
        $content = file_get_contents($url);
        $content = mb_convert_encoding($content, 'utf8', 'gb2312');

        $command = Stocks::getDb()->createCommand();

        $match = [];
        preg_match('/id="index_data_0">\s\s(.*)/', $content, $match);
        preg_match_all('/<li><span><a href=".*?">(\d+)<\/a><\/span><a href=".*?">(.*?)<\/a><\/li>/', $match[1], $stocks);
        $data = array_combine($stocks[1], $stocks[2]);
        foreach ($data as $code => $name) {
            $command->addInsert(['code' => strval($code), 'exchange' => 'sh', 'name' => $name, 'status' => 1]);
        }

        $match = [];
        preg_match('/id="index_data_1".*?>\s\s(.*)/', $content, $match);
        preg_match_all('/<li><span><a href=".*?">(\d+)<\/a><\/span><a href=".*?">(.*?)<\/a><\/li>/', $match[1], $stocks);
        $data = array_combine($stocks[1], $stocks[2]);
        foreach ($data as $code => $name) {
            $command->addInsert(['code' => strval($code), 'exchange' => 'sh', 'name' => $name, 'status' => 1]);
        }

        $match = [];
        preg_match('/id="index_data_2".*?>\s\s(.*)/', $content, $match);
        preg_match_all('/<li><span><a href=".*?">(\d+)<\/a><\/span><a href=".*?">(.*?)<\/a><\/li>/', $match[1], $stocks);
        $data = array_combine($stocks[1], $stocks[2]);
        foreach ($data as $code => $name) {
            $command->addInsert(['code' => strval($code), 'exchange' => 'sz', 'name' => $name, 'status' => 1]);
        }

        $match = [];
        preg_match('/id="index_data_3".*?>\s\s(.*)/', $content, $match);
        preg_match_all('/<li><span><a href=".*?">(\d+)<\/a><\/span><a href=".*?">(.*?)<\/a><\/li>/', $match[1], $stocks);
        $data = array_combine($stocks[1], $stocks[2]);
        foreach ($data as $code => $name) {
            $command->addInsert(['code' => strval($code), 'exchange' => 'sz', 'name' => $name, 'status' => 1]);
        }

        $command->executeBatch(Stocks::collectionName());
    }

    public function actionMinutes(string $begin, string $codes = null)
    {
        $end = date('Ym', strtotime('+1 month'));

        do {
            echo $begin . PHP_EOL;
            $year = substr($begin, 0, 4);
            $month = substr($begin, 4, 2);

            $query = Stocks::find()->select(['code', 'exchange'])->where(['status' => 1]);
            if ($codes) {
                $codes = explode(',', $codes);
                $query->andWhere(['code' => $codes]);
            }
            $stocks = $query->orderBy(['_id' => SORT_ASC])->asArray()->all();
            foreach ($stocks as $stock) {
                $code = $stock['exchange'] . $stock['code'];
                $url = sprintf($this->apiUrl, $code, $year, $month, $begin);

                try {
                    $data = file_get_contents($url);
                } catch (ErrorException $e) {
                    Stocks::updateAll(['status' => 0], ['code' => $stock['code']]);
                    continue;
                }

                $patten  = sprintf('/MLC_%s_%s_%s="(.+?)"/', $code, $year, $month);
                preg_match($patten, $data, $match);

                if (!isset($match[1])) {
                    echo $code . '没数据' . PHP_EOL;
                    continue;
                }

                $data = explode(',', $match[1]);

                $sdkJs = Yii::$app->basePath . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'sina' . DIRECTORY_SEPARATOR . 'sf_sdk.js';
                $execjs = new PhpExecJs();
                $execjs->createContextFromFile($sdkJs);

                foreach ($data as $datum) {
                    $minutes = $execjs->evalJs('decode("'.$datum.'")');
                    $stockDaily = [];
                    foreach ($minutes as $minute) {
                        $dailyDate = $minute['date'] ?? null;
                        $price = $minute['price'] ?: 0.00;

                        if ($dailyDate) {
                            if ($stockDaily) {
                                StockQuotationMinutes::getDb()->createCommand()->insert(StockQuotationMinutes::collectionName(), $stockDaily);
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
                        StockQuotationMinutes::getDb()->createCommand()->insert(StockQuotationMinutes::collectionName(), $stockDaily);
                    }
                }
            }

            $month++;
            if ($month > 12) {
                $year++;
                $month = 1;
            }

            $begin = sprintf('%d%02d', $year, $month);
        } while ($begin != $end);
    }
}
