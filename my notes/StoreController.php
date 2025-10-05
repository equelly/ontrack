<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StoreRequest;
use App\Models\Image;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;


class StoreController extends Controller
{
    //
    public function __invoke(StoreRequest $request){
        
        $data = $request->validated();
        //проверим наличие прикрепленных файлов
        if(isset($data['images'])){
            //создадим переменную для хранения массива изображений(файлов)
            $images = $data['images'];
            //и удаляем эти файлы из данных, которые сохраним в БД
            unset($data['images']);
        }
        //сохраним данные в БД
        $post = Post::firstOrCreate($data);
        //обрабатываем массив изображений(файлов) для сохранения их в storage
        //для начала проверим есть ли эти файлы иначе будет ошибка сервера
        if(isset($images) && count($images) !=0){
            foreach ($images as $image) {
                
                $nameImage = md5(Carbon::now().'_'.$image->getClientOriginalName()).'.' . $image->getClientOriginalExtension();
                //по адресу app\\storage\\public метод putFilesAs() создаст директорию с именем указанным в аргументе, второй аргумент сам файл и третий название
                $filePath = Storage::disk('public')->putFileAs('/images', $image, $nameImage);
                $prevNameImage = 'prev_'.$nameImage;
                Image::create([
                    'path'=> $filePath,
                    //хелпер url() имеет отправную точку из директории public
                    'url'=> url('/storage/'.$filePath),
                    'preview_url'=> url('/storage/images/'.$prevNameImage),
                    'post_id'=> $post->id
                ]);
                //добавим файлы в storage предварительно обработаем в классе ImageManager с драйвером Driver
                //установленный по https://mlocati.github.io/articles/php-windows-imagick.html 
                $manager = new ImageManager(new Driver());
                //метод read() прочитает содержимое файла предоставаленное file_get_contents()
                $image = $manager->read(file_get_contents($image))
                    //затем масштабируем до размеров в аргументах scale()
                    ->scale(120, 100)
                    //и сохраним 
                    ->save(storage_path('app/public/images/'.$prevNameImage));
            }

        }
        return response()->json(['message'=>'информация успешно добавлена!']);
    }
}
