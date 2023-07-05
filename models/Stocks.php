<?php

namespace app\models;

use yii\mongodb\ActiveRecord;

/**
 * This is the model class for collection "stocks".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property mixed $code
 * @property mixed $exchange
 * @property mixed $name
 */
class Stocks extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'stocks';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'code',
            'exchange',
            'name',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['code', 'exchange', 'name'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'code' => '股票代码',
            'exchange' => '所属交易所',
            'name' => '股票名称',
        ];
    }
}
