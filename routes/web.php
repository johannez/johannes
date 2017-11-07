<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::get('/', function () {

    $harvestClient = new \BestIt\Harvest\Client('https://2tabs.harvestapp.com', 'mail@2tabs.com', 'ees2aeGh123');

//    $harvestClient = new \BestIt\Harvest\Client('https://project6.harvestapp.com', 'mail@2tabs.com', 'ees2aeGh123');

//    $harvestClient->timesheet()->

    // Find all projects for this client.
    $projects = $harvestClient->projects()->findByClientId(2406402);
    d($projects);
    $timesheets = $harvestClient->timesheet()->all(true, new DateTime('-1 day'));
//    d($timesheets);

    return view('home');
});
