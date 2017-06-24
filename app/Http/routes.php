<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('uploadMedia','mediaController@uploadMedia');
Route::post('getMedia','mediaController@getMedia');
Route::post('getMediaDetails','mediaController@getMediaDetails');
Route::post('updateMedia','mediaController@updateMedia');


//Rekognition
Route::post('detectModeration','mediaController@detectModeration');
Route::get('recognizeCelebrity','mediaController@recognizeCelebrity');






