<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use AWS;

use App\Models\Media;
use App\Models\PhotoGrapher;
use App\Models\Location;


class mediaController extends Controller
{

    public function uploadMedia(Request $request){
        try{
            $uniqe_recg_id = uniqid();
            $file = $request->file('file');
            $file_ext = $file->getClientOriginalExtension() ? $file->getClientOriginalExtension() : 'png';
            $file_name = $uniqe_recg_id.'.'.$file_ext;
            $s3 = AWS::createClient('s3');
            $result = $s3->putObject(array(
                'Bucket'     => 'photofame',
                'Key'        => $file_name,
                'SourceFile' => $file->getRealPath(),
            ));

            if(isset($result) && $result['@metadata']['statusCode'] == 200){

                Media::Create([
                    'photo_grapher_id' => $request->has('photo_grapher_id') ? $request->input('photo_grapher_id') : 1,
                    'file' => $file_name, 
                    'type' => 1, 
                    'width' => $request->has('width') ? $request->input('width') : null,
                    'height' => $request->has('height') ? $request->input('height') : null, 
                    'colour_string' => $request->has('colour_string') ? $request->input('colour_string') : null,
                    'primary_colour' => $request->has('primary_colour') ? $request->input('primary_colour') : null, 
                    'background_colour' => $request->has('background_colour') ? $request->input('background_colour') : null,
                    'location_id' => $request->has('location_id') ? $request->input('location_id') : null,
                ]);

                return json_encode([
                'code' => 200,
                'message' => 'Done',
                'file_name' => $file_name,
                'file_path' => $result['ObjectURL']
                ]);

            }

        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....'
            ]);
        }
    }


    public function getMedia(Request $request){
        try{
            $offset = $request->has('offset') ? $request->input('offset') : 0;
            $media = Media::Where(['photo_grapher_id' => $request->input('photo_grapher_id')])->offset($offset)->limit(50)->get([
                'id',
                'file',
                'width',
                'height',
                'primary_colour',
                'background_colour',
                'location_id']);
            if($media->count() > 0){
                    return json_encode([
                    'code' => 200,
                    'message' => 'Done',
                    'result' => $media->toArray()
                    ]);
            }
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....'
            ]);
        }
    }

    public function getMediaDetails(Request $request){
        try{
            $media = Media::Where(['id' => $request->input('media_id')]);
            if($media->exists()){
                $media->increment('views');
                $result = $media->first([
                    'id',
                    'file',
                    'thumb',
                    'width',
                    'height',
                    'primary_colour',
                    'background_colour',
                    'views', 
                    'downloads', 
                    'shares', 
                    'favorites', 
                    'is_favorite',
                    'is_obscene',
                    'location_id',
                ]);

                return json_encode([
                    'code' => 200,
                    'message' => 'Done',
                    'result' => $result->toArray()
                ]);

            }else{
                return json_encode([
                    'code' => 400,
                    'message' => 'Ooooops.....no media file found.'
                ]);
            }
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....'
            ]);
        }

    }

    public function updateMedia(Request $request){

        try{

            if($request->has('views')){
                Media::Where(['id' => $request->input('media_id')])->increment('views');
            }

            if($request->has('downloads')){
                Media::Where(['id' => $request->input('media_id')])->increment('downloads');
            }

            if($request->has('shares')){
                Media::Where(['id' => $request->input('media_id')])->increment('shares');
            }

            if($request->has('favorites')){
                Media::Where(['id' => $request->input('media_id')])->increment('favorites');
            }

            if($request->has('is_favorite')){
                $media = Media::Where(['id' => $request->input('media_id')]);
                if($media->exists() && $media->first()->photo_grapher_id == $request->input('photo_grapher_id')){
                    $media->update(['is_favorite' => $request->has('is_favorite')]);
                }else{
                    return json_encode([
                    'code' => 400,
                    'message' => 'You are not authorized.'
                    ]);
                }
            }

            if($request->has('is_obscene')){
                Media::Where(['id' => $request->input('media_id')])->update(['is_obscene' => $request->input('is_obscene')]);
            }

            return json_encode([
                'code' => 200,
                'message' => 'Done'
            ]);
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....'
            ]);
        }
        
    }

    public function detectModeration(Request $request){
        try{
            $file_name = $request->input('name'); 
            $client = AWS::createClient('rekognition');
            $result = $client->detectModerationLabels([
                'Image' => [
                    'S3Object' => [
                        'Bucket' => 'photofame',
                        'Name' => $file_name,
                    ],
                ],
                'MinConfidence' => 70
            ]);

            if(isset($result) && is_array($result['ModerationLabels']) && count($result['ModerationLabels']) > 0){
                return 1;
            }
            return 0;
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function recognizeCelebrity(Request $request){
        $file_name = $request->input('name'); 
        $client = AWS::createClient('rekognition');
        $result = $client->recognizeCelebrities([
            'Image' => [
                'S3Object' => [
                    'Bucket' => 'photofame',
                    'Name' => $file_name,
                ]
            ],
            'MinConfidence' => 70
        ]);
        dd($result);
    }

    public function getTags($image_name){
        try{
            $client = AWS::createClient('rekognition');
            $result = $client->detectLabels([
                'Image' => [
                    'S3Object' => [
                        'Bucket' => 'photofame',
                        'Name' => '1.jpg',
                    ],
                ],
                'MaxLabels' => 10,
                'MinConfidence' => 80,
            ]);
            dd($result);
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getMessage()
            ]);
        }
    }

    
}
