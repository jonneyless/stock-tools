<?php

namespace app\models;

use yii\mongodb\ActiveRecord;

/**
 * This is the model class for collection "stock_quotation_minutes".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property mixed $code
 * @property mixed $date
 * @property mixed $opening_price
 * @property mixed $closing_price
 * @property mixed $high_price
 * @property mixed $low_price
 * @property mixed $minutes
 */
class StockQuotationMinutes extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'stock_quotation_minutes';
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
