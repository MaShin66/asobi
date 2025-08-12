<?php

namespace App\Services;
use http\Message;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Image;
use Illuminate\Support\Str;

class Helper
{

    public function getIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $ip;
    }

    public function getImage($path)
    {
        return (Storage::disk('s3')->has($path)) ? Storage::disk('s3')->temporaryUrl($path, now()->addHour(3)) : null;
    }

    public function getDownloadImage($path)
    {
        return (Storage::disk('s3')->has($path)) ? Storage::disk('s3')->get($path) : null;
    }

    public function deleteImage($path): bool
    {
        $returnBool = false;
        if(Storage::disk('s3')->exists($path)) {
            Storage::disk('s3')->delete($path);
            $returnBool = true;
        }
        return $returnBool;
    }

    public function getSmsToken(): string
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.bizppurio.com/v1/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-type: application/json; charset=utf-8',
                'Authorization: Basic '.base64_encode(env('SMS_API_ID').":".env('SMS_API_PW'))
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $rs = json_decode($response, true);
        return $rs['accesstoken'] ?? '';
    }

    public function sendSms($phoneNumber='01000000000', $message="SMS 테스트"): bool
    {
        $token = $this->getSmsToken();
        if ($token == '') return false;

//        $this->log('token='.$token, [], 'sms');

        $data = [
            "account" => env('SMS_API_ID'),
            "refkey" => "1234",
            "type" => "at",
            "from" => env('SMS_API_FROM'),
            "to" => $phoneNumber,
            "content" => [
                "at" => [
                    "senderkey" => "12345",
                    "templatecode" => "template",
                    "message" => $message,
//                    "button" => [
//                        "name" => "웹 링크 버튼",
//                        "type" => "WL",
//                        "url_mobile" => env('APP_URL'),
//                        "url_pc" => env('APP_URL'),
//                    ]
                ]
            ],
            "resend" => ["first" => "sms"],
            "recontent" => ["sms" => ["message" => $message]],
        ];

//        echo 'Request :';
//        echo '<pre>';
//        print_r($data);
//        echo '</pre>';

        $json_data = json_encode($data, JSON_UNESCAPED_SLASHES);

        $url = 'https://dev-api.bizppurio.com/v3/message';

        if (!env('SMS_API_DEBUG')) {
            $url = 'https://api.bizppurio.com/v3/message';
        }

        $oCurl = curl_init();
        curl_setopt($oCurl,CURLOPT_URL,$url);
        curl_setopt($oCurl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($oCurl,CURLOPT_NOSIGNAL, 1);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($oCurl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER,
            array('Accept: application/json', 'Content-Type: application/json',
                'Authorization: Bearer '. $token));
        curl_setopt($oCurl, CURLOPT_VERBOSE, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($oCurl, CURLOPT_TIMEOUT, 3);

        $response = curl_exec($oCurl);
        $curl_errno = curl_errno($oCurl);
        $curl_error = curl_error($oCurl);

        curl_close($oCurl);

        $this->log('response='.$response, [], 'sms');
        if (env('SMS_API_DEBUG')) {
            $this->log('response='.$response, [], 'sms');
        } else {
            $this->log('response='.$response, [], 'sms','info');
        }
        $rs = json_decode($response, true);

//        echo 'Response :';
//        echo '<pre>';
//        print_r(json_decode($response));
//        print_r($curl_error);
//        echo '</pre>';

        $code = $rs['code'] ?? '';
        return $code == "1000";
    }

    public function log(string $message, $context = [], string $preFix = "", string $level = 'debug')
    {
        $message = "[{$preFix}] {$message}";

        switch ($level) {
            case 'debug':
                Log::debug($message, $context);
                break;
            case 'emergency':
                Log::emergency($message, $context);
                break;
            case 'alert':
                Log::alert($message, $context);
                break;
            case 'critical':
                Log::critical($message, $context);
                break;
            case 'error':
                Log::error($message, $context);
                break;
            case 'warning':
                Log::warning($message, $context);
                break;
            case 'notice':
                Log::notice($message, $context);
                break;
            case 'info':
                Log::info($message, $context);
                break;
            default:
                Log::debug($message, $context);
                break;
        }
    }

    public function vardump($data): void
    {
        echo "<pre>";
        var_dump($data);
        echo "</pre>";
    }

    public function alert($msg="", $url=""): void
    {
        echo <<<EEE
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no">

            <!-- jalert -->
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js"></script>
            <script src="/js/jalert.js"></script>

            <link rel="stylesheet" href="/css/boot_custom.css">
            <link rel="stylesheet" href="/css/boot_custom.css.map">
            <link rel="stylesheet" href="/css/custom.css">
            <link rel="stylesheet" href="/css/common.css">
            <link rel="stylesheet" href="/css/design.css">
            <link rel="stylesheet" href="/css/design_mo.css">
EEE;

        if ($url == "") {
            echo "<script type=\"text/javascript\">
                    $(document).ready(function(){
                        jalert_url('".$msg."', 'back', '알림');
                    });
                </script>";
        } else {
            echo "<script type=\"text/javascript\">
                    $(document).ready(function(){
                        jalert_url('".$msg."', '".$url."', '알림');
                    });
                </script>";
        }
        exit;
    }

    public function getValidError($validator): string
    {
        $err_txt = "";
        if($validator->fails()){
            $errors = $validator->errors();
            foreach ($errors->all() as $message) {
                $err_txt .= $message."\\n";
            }
        }
        return $err_txt;
    }

    public function getClientIp(): string
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_X_REMOTE_ADDR'])) {
            return $_SERVER['HTTP_X_REMOTE_ADDR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }

    public function dayOfKo(Carbon $date, int $mode=1): string
    {
        $data = "";
        switch ($date->dayOfWeek) {
            case Carbon::SUNDAY: $data = "일"; break;
            case Carbon::MONDAY: $data = "월"; break;
            case Carbon::TUESDAY: $data = "화"; break;
            case Carbon::WEDNESDAY: $data = "수"; break;
            case Carbon::THURSDAY: $data = "목"; break;
            case Carbon::FRIDAY: $data = "금"; break;
            case Carbon::SATURDAY: $data = "토"; break;
        }
        if ($mode == '2' && $data != "") {
            $data .= "요일";
        }
        return $data;
    }

    public function dateOfKoAmPm(Carbon $date): string
    {
        $data = $date->format('m.d') . " ";
        $hour = $date->format('H');
        if ($hour > 12) {
            $hour = $hour - 12;
            $data .= "오후 ";
        } else {
            $data .= "오전 ";
        }
        $data .= sprintf('%02d',$hour).":".$date->format('i');
        return $data;
    }

    public function hypenPhone(string $hp): string
    {
        $hp = str_replace('-','',$hp);
        $hp = str_replace('_','',$hp);
        $length = mb_strlen($hp);
        $tmp = '';
        if ($length < 4) {
            return $hp;
        } else if($length < 7) {
            $tmp .= substr($hp,0,3);
            $tmp .= '-';
            $tmp .= substr($hp,3);
        } else if($length < 11) {
            $tmp .= substr($hp,0,3);
            $tmp .= '-';
            $tmp .= substr($hp,3,3);
            $tmp .= '-';
            $tmp .= substr($hp,6);
        } else {
            $tmp .= substr($hp,0,3);
            $tmp .= '-';
            $tmp .= substr($hp,3,4);
            $tmp .= '-';
            $tmp .= substr($hp,7);
        }
        return $tmp;
    }

    public function getUserType(string $type): string
    {
        $data = "";
        switch ($type)
        {
            case "a": $data = "본사"; break;
            case "h": $data = "지사"; break;
            case "m": $data = "교육원"; break;
            case "s": $data = "학부모"; break;
        }
        return $data;
    }

    public function getEditorImgs($contents): array
    {
        $arr = [];
        $searchImgPattern = "/<img[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i";
        if (preg_match_all($searchImgPattern, $contents, $match) != 0) {
            $matchArr = $match[0] ?? [];
            if (count($matchArr) > 0) {
                foreach ($matchArr as $l) {
                    preg_match($searchImgPattern, $l, $mat);
                    $parse = parse_url($mat[1] ?? "");
                    $path = $parse['path'] ?? '';
                    if ($path != "") {
                        if (strpos($path,"app/editor") === false) {
                            $arr[] = $mat[1] ?? "";
                        } else {
                            $arr[] = ltrim($path, "/");
                        }
                    }
                }
            }
        }
        return $arr;
    }

    public function replaceEditorImgs($contents)
    {
        $return = $contents;
        $searchImgPattern = "/<img[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i";
        if (preg_match($searchImgPattern, $contents, $match) != 0) {
            $matchFn = function($mat) {
                try {
                    $parse = parse_url($mat[1] ?? "");
                    $path = $parse['path'] ?? '';
                    if (strpos($path,"app/editor") === false) {
                        $s3_path = $mat[1] ?? "";
                    } else {
                        $src = ltrim($path, "/");
                        $s3_path = $this->getImage($src);
                    }
                    return str_replace($mat[1] ?? "", $s3_path, $mat[0] ?? "");
                } catch (\Exception $e) {
                    return $mat[0] ?? "";
                }
            };
            $return = preg_replace_callback($searchImgPattern,$matchFn,$contents);
        }
        return $return;
    }

    public function replaceFirstEditorImg($contents)
    {
        $return = "";
        $imgs = $this->getEditorImgs($contents);
        $src = $imgs[0] ?? '';
        if ($src != "") {
            if (strpos($src,"app/editor") !== false || strpos($src,"asobi-new-app.s3.ap-northeast-2.amazonaws.com") !== false) {
                $return = $this->getImage($src);
            } else {
                $return = $src;
            }
        }
        return $return;
    }

    public function putResizeS3(string $s3Path, \Illuminate\Http\UploadedFile $uploadedFile, int $width=1920, int $height=1920, $fixed=false): string
    {
        // 기존 코드
        // $path = Storage::disk('s3')->put($s3Path, $uploadedFile);
        $extension = $uploadedFile->getClientOriginalExtension();
        $img = Image::make($uploadedFile->path());
        $resizeImage = $img->resize($width, $height, function ($constraint) use ($fixed) {
            if (! $fixed) {
                $constraint->aspectRatio();
                $constraint->upsize();
            }
        })->encode($extension);

        $path = $s3Path.'/'.Str::uuid().".".$extension;
        $storageReturn = Storage::disk('s3')->put($path, $resizeImage->stream()->__toString());
        if ($storageReturn === false) {
            throw new \Exception('s3 업로드에 실패하였습니다.');
        }
        return $path;
    }

    public function putS3(string $s3Path, \Illuminate\Http\UploadedFile $uploadedFile) {
        $path = Storage::disk('s3')->put($s3Path, $uploadedFile);
        return $path;
    }

    public function putVideoS3(string $s3Path, \Illuminate\Http\UploadedFile $uploadedFile): string
    {
        $file_path = Storage::disk('s3')->put($s3Path, $uploadedFile);
        if ($file_path === false) {
            throw new \Exception('s3 업로드에 실패하였습니다.');
        }
        return $file_path;
    }

    //접근 제한 권한체크
    public function checkUserType(array $userType=['s'], string $msg='잘못된 접근입니다.'): void
    {
        $sessionUserType = $this->getUsertType();
        if (!in_array($sessionUserType, $userType)) {
            $this->alert($msg);
        }
    }

    public function getUsertType(): string
    {
        return session()->get('auth')['user_type'] ?? '';
    }

    public function getUsertId(): string
    {
        return session()->get('auth')['user_id'] ?? '';
    }

    public function getErrorMsg($error): string
    {
        $msg = "";
        if (is_array($error) && count($error) > 0) {
            foreach ($error as $l) {
                $msg = $l[0] ?? "";
                break;
            }
        } else {
            $msg = $error;
        }
        return $msg;
    }

    public function rotateImage($file)
    {
        // 파일이 존재하는지 확인
        if (!file_exists($file)) {
            \Log::error("파일이 존재하지 않음: {$file}");
            return $file;
        }
    
        // 파일이 JPEG인지 확인 (exif_imagetype을 두 번 실행하지 않도록 변경)
        $imageType = exif_imagetype($file);
        if ($imageType !== IMAGETYPE_JPEG) {
            \Log::warning("JPEG 파일이 아님: {$file}");
            return $file;
        }
    
        // EXIF 데이터 읽기 (경고 방지를 위해 @ 사용)
        $exif = @exif_read_data($file);
        if (!$exif) {
            \Log::warning("EXIF 데이터가 없음 또는 잘못됨: {$file}");
            return $file;
        }
    
        // EXIF Orientation 값이 있는지 확인
        if (!isset($exif['Orientation'])) {
            \Log::warning("Orientation 정보가 없음: {$file}");
            return $file;
        }
    
        $orientation = $exif['Orientation'];
    
        // Orientation이 1(정방향)일 경우 회전할 필요 없음
        if ($orientation == 1) {
            return $file;
        }
    
        // 이미지 로드
        $img = imagecreatefromjpeg($file);
        if (!$img) {
            \Log::error("이미지를 불러올 수 없음: {$file}");
            return $file;
        }
    
        // 회전 처리
        if (in_array($orientation, [3, 4])) {
            $img = imagerotate($img, 180, 0);
        }
        if (in_array($orientation, [5, 6])) {
            $img = imagerotate($img, -90, 0);
        }
        if (in_array($orientation, [7, 8])) {
            $img = imagerotate($img, 90, 0);
        }
        if (in_array($orientation, [2, 5, 7, 4])) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        }
    
        // 임시 파일 저장
        $tempPath = tempnam(sys_get_temp_dir(), 'rotated_image');
        imagejpeg($img, $tempPath);
        imagedestroy($img); // 메모리 해제
    
        // 원본 파일 덮어쓰기
        if (!copy($tempPath, $file)) {
            \Log::error("파일 덮어쓰기 실패: {$file}");
        }
    
        unlink($tempPath); // 임시 파일 삭제
    
        return $file;
    }
}
