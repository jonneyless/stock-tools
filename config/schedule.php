<?php
/**
 * @var \omnilight\scheduling\Schedule $schedule
 */

$schedule->command('grab/daily')->dailyAt('08:00')->sendOutputTo(Yii::getAlias('@runtime/logs/schedule.log'));