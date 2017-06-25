<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use AWS;

use App\Models\Media;
use App\Models\PhotoGrapher;
use App\Models\Location;
use App\Models\Tag;
use Aws\Rekognition\Exception\RekognitionException;

class mediaController extends Controller
{

    public function uploadMedia(Request $request){
        try{
            $uniqe_recg_id = uniqid();
            $file = $request->file('file');
            $file_ext = 'png';//$file->getClientOriginalExtension() ? $file->getClientOriginalExtension() : 'png';
            $file_name = $uniqe_recg_id.'.'.$file_ext;
            $s3 = AWS::createClient('s3');
            $result = $s3->putObject(array(
                'Bucket'     => 'photofame',
                'Key'        => $file_name,
                'SourceFile' => $file->getRealPath(),
            ));

            if(isset($result) && $result['@metadata']['statusCode'] == 200){

               $media = Media::Create([
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

                $this->detectModeration($media->id);
                $this->recognizeCelebrity($media->id);
                $this->getTags($media->id);
                return $this->getMediaDetailsById($media->id);
                /*
                return json_encode([
                'code' => 200,
                'message' => 'Done',
                'file_name' => $file_name,
                'file_path' => $result['ObjectURL']
                ]);
                */
            }

        }catch(RekognitionException $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getAwsErrorMessage()
            ]);
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function getMedia(Request $request){
        try{
            $offset = $request->has('offset') ? $request->input('offset') : 0;
            //->offset($offset)->limit(50)
            $media = Media::Where(['photo_grapher_id' => $request->input('photo_grapher_id')])->orderByRaw('RAND()')->get([
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
                $tags = $details = array();
                $details = $media->first([
                    'id',
                    'photo_grapher_id',
                    'file', 
                    'thumb', 
                    'type', 
                    'width', 
                    'height', 
                    'colour_string', 
                    'primary_colour', 
                    'background_colour', 
                    'location_id', 
                    'views', 
                    'downloads', 
                    'shares', 
                    'favorites', 
                    'is_favorite',
                    'is_obscene',
                    'celebrity_name'
                ]);
                $tags = Tag::Where(['media_id' => $request->input('media_id')]);
                if($tags->exists()){
                    $tags = $tags->get(['name'])->toArray();
                    foreach($tags as $each_tag){
                        $final_tags[] = $each_tag['name']; 
                    }
                }

                return json_encode([
                    'code' => 200,
                    'message' => 'Done',
                    'result' => [
                        'details' => $details->toArray(),
                        'tags' => $final_tags
                    ]
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
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getMessage()
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

    public function detectModeration($media_id){
        try{
            $media = Media::Where(['id' => $media_id]);
            if(!$media->exists()){
                return false;
            }
            $client = AWS::createClient('rekognition');
            $result = $client->detectModerationLabels([
                'Image' => [
                    'S3Object' => [
                        'Bucket' => 'photofame',
                        'Name' => $media->first()->file,
                    ],
                ],
                'MinConfidence' => 70
            ]);
            if(isset($result) && is_array($result['ModerationLabels']) && count($result['ModerationLabels']) > 0){
                $media->update([
                    'is_obscene' => 1
                ]);
                return true;
            }
            return false;
        }catch(RekognitionException $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getAwsErrorMessage()
            ]);
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function recognizeCelebrity($media_id){
        try{
            $media = Media::Where(['id' => $media_id]);
            if(!$media->exists()){
                return false;
            }
            $client = AWS::createClient('rekognition');
            $result = $client->recognizeCelebrities([
                'Image' => [
                    'S3Object' => [
                        'Bucket' => 'photofame',
                        'Name' => $media->first()->file,
                    ]
                ]
            ]);
            $celebrity_name = '';
            if(isset($result) && is_array($result['CelebrityFaces']) && count($result['CelebrityFaces']) > 0){
                $celebrity_name = $result['CelebrityFaces'][0]['Name'];
                $media->update([
                    'celebrity_name' => $celebrity_name
                ]);
                Tag::Create([
                    'media_id' => $media_id,
                    'photo_grapher_id' => $media->first()->photo_grapher_id,
                    'name' => $celebrity_name
                ]);
                return true;
            }
            return false;
        }catch(RekognitionException $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getAwsErrorMessage()
            ]);
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function getTags($media_id){
        try{
            if(!is_numeric($media_id)) return;
            $media = Media::Where(['id' => $media_id]);
            if(!$media->exists()) return;
            $client = AWS::createClient('rekognition');
            $result = $client->detectLabels([
                'Image' => [
                    'S3Object' => [
                        'Bucket' => 'photofame',
                        'Name' => $media->first()->file,
                    ],
                ],
                'MaxLabels' => 30,
                'MinConfidence' => 70,
            ]);

            if(isset($result) && is_array($result['Labels']) && count($result['Labels']) > 0){
                foreach($result['Labels'] as $tag){
                    Tag::Create([
                        'name' => $tag['Name'],
                        'media_id' => $media->first()->id,
                        'photo_grapher_id' => $media->first()->photo_grapher_id
                    ]);
                }
                return;
            }
            return;
        }catch(RekognitionException $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getAwsErrorMessage()
            ]);
        }catch(\Exception $e){
            return json_encode([
                'code' => 400,
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getMessage()
            ]);
        }
    }

    protected function getMediaDetailsById($media_id){
        try{
            $media = Media::Where(['id' => $media_id]);
            if($media->exists()){
                $media->increment('views');
                $tags = $details = $final_tags = array();
                $details = $media->first([
                    'id',
                    'photo_grapher_id',
                    'file', 
                    'thumb', 
                    'type', 
                    'width', 
                    'height', 
                    'colour_string', 
                    'primary_colour', 
                    'background_colour', 
                    'location_id', 
                    'views', 
                    'downloads', 
                    'shares', 
                    'favorites', 
                    'is_favorite',
                    'is_obscene',
                    'celebrity_name'
                ]);
                $tags = Tag::Where(['media_id' => $media_id]);
                if($tags->exists()){
                    $tags = $tags->get(['name'])->toArray();
                    foreach($tags as $each_tag){
                        $final_tags[] = $each_tag['name']; 
                    }
                }

                return json_encode([
                    'code' => 200,
                    'message' => 'Done',
                    'result' => [
                        'details' => $details->toArray(),
                        'tags' => $final_tags
                    ]
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
                'message' => 'Ooops something went wrong....',
                'exception' => $e->getMessage()
            ]);
        }

    }
    
}
