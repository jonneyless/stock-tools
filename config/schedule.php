<?php
/**
 * @var \omnilight\scheduling\Schedule $schedule
 */

$schedule->command('grab/daily')->dailyAt('10:40')->sendOutputTo(Yii::getAlias('@runtime/logs/schedule.log'));