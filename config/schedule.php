<?php
/**
 * @var \omnilight\scheduling\Schedule $schedule
 */

$schedule->command('grab/daily')->dailyAt('18:36')->sendOutputTo(Yii::getAlias('@runtime/logs/schedule.log'));