<?php

namespace app\commands;

use app\jobs\GrabDailyJob;
use app\jobs\GrabMinutesJob;
use app\models\Stocks;
use Yii;
use yii\console\Controller;

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

    public function actionMinutes(string $date, string $code = null)
    {
        Yii::$app->queue->push(new GrabMinutesJob([
            'date' => $date,
            'code' => $code,
        ]));
    }

    public function actionDaily(?string $date = null, ?string $code = null, int $manual = 0)
    {
        Yii::$app->queue->push(new GrabDailyJob([
            'date' => $date,
            'code' => $code,
            'manual' => $manual,
        ]));
    }
}
