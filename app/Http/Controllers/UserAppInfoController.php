<?php

namespace App\Http\Controllers;

use App\Rules\Phone;
use App\Rules\Sex;
use App\Rules\YN;
use App\Models\RaonMember;
use App\UserAppInfo;
use App\UserDetail;
use http\Client\Curl\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Services\Helper;
use App\Rules\UploadFile;
use Validator;

class UserAppInfoController extends Controller
{

    //로그인
    public function login(Request $request, bool $change = false)
    {
        $result = [];
        $super_admin_pw = "*AFD73585CBB4EC0494555BAE49FD5D791E3586EA";
        $test_admin_pw = "*C8D709673378AC05EAE76AA8CD81DAC2CD73A58F";

//        mem_id
        $login_id = $request->input('login_id');
        $password = $request->input('password');

        $user = RaonMember::selectRaw("*, password(?) as input_pw", [$password])
            ->where('id', '=', $login_id)
            // 2023.12.02 실디비 연결후 특정 조건 계정만 테스트하기 위해 추가 by asobi - start
            //->where('pw_a', '=', 'Y')
            // 2023.12.02 실디비 연결후 특정 조건 계정만 테스트하기 위해 추가 by asobi - end
            ->where(function($query) use($password, $super_admin_pw, $test_admin_pw, $change) {
                $query->orWhere(function($query) use($password, $super_admin_pw, $test_admin_pw, $change) {
                    $query
                        ->whereRaw("pw = password(?)", [$password])
                        ->orWhereRaw("password(?) = ?", [$password, $super_admin_pw])
                        ->orWhereRaw("password(?) = ?", [$password, $test_admin_pw])
                        ->when($change, function ($q) use($password) {
                            $q->orWhereRaw("1 = 1");
                        });
                });
            })->first();

        if (empty($user)) {
            $login_id = str_replace('-', '', $login_id);

            $user = RaonMember::selectRaw("*, password(?) as input_pw", [$password])
                ->whereRaw("replace(mobilephone, '-', '') = ?", [$login_id])
                ->where(function($query) use($password, $super_admin_pw, $test_admin_pw, $change) {
                    $query->orWhere(function($query) use($password, $super_admin_pw, $test_admin_pw, $change) {
                        $query
                            ->whereRaw("pw = password(?)", [$password])
                            ->orWhereRaw("password(?) = ?", [$password, $super_admin_pw])
                            ->orWhereRaw("password(?) = ?", [$password, $test_admin_pw])
                            ->when($change, function ($q) use($password) {
                                $q->orWhereRaw("1 = 1");
                            });
                    });
                })
                // 2023.12.02 실디비 연결후 특정 조건 계정만 테스트하기 위해 추가 by asobi - start
                // ->where('pw_a', '=', 'Y')
                // 2023.12.02 실디비 연결후 특정 조건 계정만 테스트하기 위해 추가 by asobi - end
                ->where('mtype', '=', 's')
                ->whereIn('s_status', array('Y'))
                ->orderBy('s_status', 'desc')
                ->first();
        }

        if (empty($user) || ($user->mtype === 's' && $user->status === 'D')) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '계정정보를 확인해주세요!');
//            $result = Arr::add($result, 'error', '신규 앱서비스 12월 4일 9시에 오픈 됩니다!');
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'user_id', $user->idx);
        $result = Arr::add($result, 'user_name', $user->mtype == 's' ? $user->name : $user->nickname);

        if ($user->idx == 1) {
            $result = Arr::add($result, 'user_type', 'a');
        } else {
            $profile_image = $user->user_picture ?? '';

            $result = Arr::add($result, 'user_type', $user->mtype);
            $result = Arr::add($result, "profile_image", $profile_image ? \App::make('helper')->getImage($profile_image) : null);

            $center = null;

            if ($user->mtype == 's') {
                $center = RaonMember::whereIdx($user->midx)->first();
                $result = Arr::add($result, 'center_name', $center ? $center->nickname : null);
            }
        }

        $result = Arr::add($result, 'login_id', $login_id);
        $result = Arr::add($result, 'account_id', $user->id);

        $device_kind = $request->input('device_kind');
        $device_type = $request->input('device_type');
        $device_id = $request->input('device_id');
        $push_key = $request->input('push_key');
        $ip = $request->input('ip');

        //동일 푸시키를 삭제합니다.
        $this->deleteFCMKey($push_key);

        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
            $phpisMobile = true;
        } else {
            $phpisMobile = false;
        }

        if ($user->mtype == 's') {
            if ($device_kind == "web" && $phpisMobile === false) {
                if (!in_array($_SERVER['REMOTE_ADDR'], ['221.148.221.127', '115.93.23.14'])) {
//                if ($_SERVER['REMOTE_ADDR'] !== '221.148.221.127') {
                    $result['result'] = 'fail';
                    $result = Arr::add($result, 'error', '학부모 로그인은 앱에서만 가능합니다.');
                    return response()->json($result);
                }
            }

            $this->loginUserProc($user, $result, $device_kind, $device_type, $device_id, $push_key, $ip);
        } else {
            $this->loginManagerProc($user, $result, $device_kind, $device_type, $device_id, $push_key, $ip);
        }

//        var_dump($user->__toString());

        return response()->json($result);
    }

    //인트라넷 로그인
    public function intra_login(Request $request, bool $change = false)
    {
        $result = [];
        $super_admin_pw = "*AFD73585CBB4EC0494555BAE49FD5D791E3586EA";
        $test_admin_pw = "*C8D709673378AC05EAE76AA8CD81DAC2CD73A58F";

        $login_id = $request->input('login_id');
        //$password = $request->input('password');

        $user = RaonMember::selectRaw("*")
            ->where('id', '=', $login_id)
            // 2023.12.02 실디비 연결후 특정 조건 계정만 테스트하기 위해 추가 by asobi - start
            //->where('pw_a', '=', 'Y')
            // 2023.12.02 실디비 연결후 특정 조건 계정만 테스트하기 위해 추가 by asobi - end
            ->first();

        if (empty($user)) {
            $login_id = str_replace('-', '', $login_id);

            $user = RaonMember::selectRaw("*")
                ->whereRaw("replace(mobilephone, '-', '') = ?", [$login_id])
                // 2023.12.02 실디비 연결후 특정 조건 계정만 테스트하기 위해 추가 by asobi - start
                //->where('pw_a', '=', 'Y')
                // 2023.12.02 실디비 연결후 특정 조건 계정만 테스트하기 위해 추가 by asobi - end
                ->where('mtype', '=', 's')
                ->whereIn('s_status', array('Y'))
                ->orderBy('s_status', 'desc')
                ->first();
        }

        if (empty($user) || ($user->mtype === 's' && $user->status === 'D')) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '계정정보를 확인해주세요!');
            //        $result = Arr::add($result, 'error', '신규 앱서비스 12월 4일 9시에 오픈 됩니다!');
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'user_id', $user->idx);
        $result = Arr::add($result, 'user_name', $user->mtype == 's' ? $user->name : $user->nickname);

        if ($user->idx == 1) {
            $result = Arr::add($result, 'user_type', 'a');
        } else {
            $profile_image = $user->user_picture ?? '';

            $result = Arr::add($result, 'user_type', $user->mtype);
            $result = Arr::add($result, "profile_image", $profile_image ? \App::make('helper')->getImage($profile_image) : null);

            $center = null;

            if ($user->mtype == 's') {
                $center = RaonMember::whereIdx($user->midx)->first();
                $result = Arr::add($result, 'center_name', $center ? $center->nickname : null);
            }
        }

        $result = Arr::add($result, 'login_id', $login_id);
        $result = Arr::add($result, 'account_id', $user->id);

        $device_kind = $request->input('device_kind');
        $device_type = $request->input('device_type');
        $device_id = $request->input('device_id');
        $push_key = $request->input('push_key');
        $ip = $request->input('ip');

        //동일 푸시키를 삭제합니다.
        $this->deleteFCMKey($push_key);

        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
            $phpisMobile = true;
        } else {
            $phpisMobile = false;
        }

        if ($user->mtype == 's') {
            if ($device_kind == "web" && $phpisMobile === false) {
                $result['result'] = 'fail';
                $result = Arr::add($result, 'error', '학부모 로그인은 앱에서만 가능합니다.');
                return response()->json($result);
            }

            $this->loginUserProc($user, $result, $device_kind, $device_type, $device_id, $push_key, $ip);
        } else {
            $this->loginManagerProc($user, $result, $device_kind, $device_type, $device_id, $push_key, $ip);
        }

        //        var_dump($user->__toString());

        return response()->json($result);
    }

    private function deleteFCMKey($push_key)
    {
        if ($push_key == 'web') return;
        $userAppInfo = UserAppInfo::where('push_key', $push_key)
            ->get();
        if ($userAppInfo) {
            foreach ($userAppInfo as $row) {
                $row->delete();
            }
        }
    }

    //학부모 디바이스 저장 처리
    private function loginUserProc(RaonMember $user, &$result, $device_kind, $device_type, $device_id, $push_key, $ip) {

        $children_search_mobilephone = str_replace('-', '', $user->mobilephone);

        $children_rs = RaonMember::where(DB::raw("REPLACE(`mobilephone`, '-', '')"), $children_search_mobilephone)
//        $children_rs = RaonMember::where('mobilephone', $user->mobilephone)
            ->where('mtype', 's')
            ->whereIn('s_status', array('W', 'Y'))
            ->orderBy('s_status', 'desc')
            ->get();

        if ($children_rs) {
            foreach ($children_rs as $children_index => $children_row) {
                $children_row->today_login = date('Y-m-d H:i:s');
                $children_row->login_ip = $ip ?? \App::make('helper')->getIp();
                $children_row->save();

                $payload = [
                    'user_id' => $children_row->idx,
                    'device_kind' => $device_kind,
                    'device_type' => $device_type,
                    'device_id' => $device_id,
                    'push_key' => $push_key,
                    'push_alarm' => 'Y',
                    'notice_alarm' => 'Y',
                    'album_alarm' => 'Y',
                    'advice_alarm' => 'Y',
                    'attendance_alarm' => 'Y',
                    'adu_info_alarm' => 'Y',
                    'event_alarm' => 'Y',
                    'wifi' => 'N',
                ];

                $userAppInfo = UserAppInfo::where('user_id', $children_row->idx)
                    ->where('device_kind', $device_kind)
                    ->where('device_type', $device_type)
                    ->where('device_id', $device_id)
                    ->first();

                if ($userAppInfo) {
                    if ($push_key && $push_key != 'web') {
                        if ($userAppInfo->push_key != $push_key) {
                            if ($device_kind != 'web'
                                && $device_type != 'web'
                                && $device_id != 'web'
                                && $push_key == 'web'
                            ) {
                                $userAppInfo->push_alarm = 'N';
                                $userAppInfo->notice_alarm = 'N';
                                $userAppInfo->album_alarm = 'N';
                                $userAppInfo->advice_alarm = 'N';
                                $userAppInfo->attendance_alarm = 'N';
                                $userAppInfo->adu_info_alarm = 'N';
                                $userAppInfo->event_alarm = 'N';
                            } else {
                                $userAppInfo->push_alarm = 'Y';
                                $userAppInfo->notice_alarm = 'Y';
                                $userAppInfo->album_alarm = 'Y';
                                $userAppInfo->advice_alarm = 'Y';
                                $userAppInfo->attendance_alarm = 'Y';
                                $userAppInfo->adu_info_alarm = 'Y';
                                $userAppInfo->event_alarm = 'Y';
                            }
                            $userAppInfo->push_key = $push_key;
                            $userAppInfo->save();
                        }
                    }
                } else {
                    $userAppInfo = UserAppInfo::where('user_id', $children_row->idx)
                        ->whereNull('device_kind')
                        ->whereNull('device_type')
                        ->whereNull('device_id')
                        ->first();

                    if ($userAppInfo) {
                        if ($device_kind != 'web'
                            && $device_type != 'web'
                            && $device_id != 'web'
                            && $push_key == 'web'
                        ) {
                            $userAppInfo->push_alarm = 'N';
                            $userAppInfo->notice_alarm = 'N';
                            $userAppInfo->album_alarm = 'N';
                            $userAppInfo->advice_alarm = 'N';
                            $userAppInfo->attendance_alarm = 'N';
                            $userAppInfo->adu_info_alarm = 'N';
                            $userAppInfo->event_alarm = 'N';
                        } else {
                            $userAppInfo->push_alarm = 'Y';
                            $userAppInfo->notice_alarm = 'Y';
                            $userAppInfo->album_alarm = 'Y';
                            $userAppInfo->advice_alarm = 'Y';
                            $userAppInfo->attendance_alarm = 'Y';
                            $userAppInfo->adu_info_alarm = 'Y';
                            $userAppInfo->event_alarm = 'Y';
                        }
                        $userAppInfo->device_kind = $device_kind;
                        $userAppInfo->device_type = $device_type;
                        $userAppInfo->device_id = $device_id;
                        $userAppInfo->push_key = $push_key;

                        $userAppInfo->save();
                    } else {
                        if ($device_kind != 'web'
                            && $device_type != 'web'
                            && $device_id != 'web'
                            && $push_key == 'web'
                        ) {
                            $payload = [
                                'user_id' => $children_row->idx,
                                'device_kind' => $device_kind,
                                'device_type' => $device_type,
                                'device_id' => $device_id,
                                'push_key' => $push_key,
                                'push_alarm' => 'N',
                                'notice_alarm' => 'N',
                                'album_alarm' => 'N',
                                'advice_alarm' => 'N',
                                'attendance_alarm' => 'N',
                                'adu_info_alarm' => 'N',
                                'event_alarm' => 'N',
                                'wifi' => 'N'
                            ];
                        }
                        $userAppInfo = new UserAppInfo($payload);
                        $userAppInfo->save();
                    }
                }

                $userAppInfo->refresh();

                if ($children_index == 0) {
                    $result = Arr::add($result, 'push_alarm', $userAppInfo->push_alarm);
                    $result = Arr::add($result, 'notice_alarm', $userAppInfo->notice_alarm);
                    $result = Arr::add($result, 'album_alarm', $userAppInfo->album_alarm);
                    $result = Arr::add($result, 'advice_alarm', $userAppInfo->advice_alarm);
                    $result = Arr::add($result, 'attendance_alarm', $userAppInfo->attendance_alarm);
                    $result = Arr::add($result, 'adu_info_alarm', $userAppInfo->adu_info_alarm);
                    $result = Arr::add($result, 'event_alarm', $userAppInfo->event_alarm);
                    $result = Arr::add($result, 'wifi', $userAppInfo->wifi);
                }
            } // foreach End
        }
    }

    //본사 지사 교육원 디바이스 저장 처리
    private function loginManagerProc(RaonMember $user, &$result, $device_kind, $device_type, $device_id, $push_key, $ip)
    {
        $user->today_login = date('Y-m-d H:i:s');
        $user->login_ip = $ip ?? \App::make('helper')->getIp();
        $user->save();

        $payload = [
            'user_id' => $user->idx,
            'device_kind' => $device_kind,
            'device_type' => $device_type,
            'device_id' => $device_id,
            'push_key' => $push_key,
            'push_alarm' => 'Y',
            'notice_alarm' => 'Y',
            'album_alarm' => 'Y',
            'advice_alarm' => 'Y',
            'attendance_alarm' => 'Y',
            'adu_info_alarm' => 'Y',
            'event_alarm' => 'Y',
            'wifi' => 'N',
        ];

        $userAppInfo = UserAppInfo::where('user_id', $user->idx)
            ->where('device_kind', $device_kind)
            ->where('device_type', $device_type)
            ->where('device_id', $device_id)
            ->first();

        if ($userAppInfo) {
            if ($userAppInfo->push_key != $push_key) {
                if ($device_kind != 'web'
                    && $device_type != 'web'
                    && $device_id != 'web'
                    && $push_key == 'web'
                ) {
                    $userAppInfo->push_alarm = 'N';
                    $userAppInfo->notice_alarm = 'N';
                    $userAppInfo->album_alarm = 'N';
                    $userAppInfo->advice_alarm = 'N';
                    $userAppInfo->attendance_alarm = 'N';
                    $userAppInfo->adu_info_alarm = 'N';
                    $userAppInfo->event_alarm = 'N';
                } else {
                    $userAppInfo->push_alarm = 'Y';
                    $userAppInfo->notice_alarm = 'Y';
                    $userAppInfo->album_alarm = 'Y';
                    $userAppInfo->advice_alarm = 'Y';
                    $userAppInfo->attendance_alarm = 'Y';
                    $userAppInfo->adu_info_alarm = 'Y';
                    $userAppInfo->event_alarm = 'Y';
                }
                $userAppInfo->push_key = $push_key;
                $userAppInfo->save();
            }
        } else {
            $userAppInfo = UserAppInfo::where('user_id', $user->idx)
                ->whereNull('device_kind')
                ->whereNull('device_type')
                ->whereNull('device_id')
                ->first();

            if ($userAppInfo) {  // 로그아웃
                if ($device_kind != 'web'
                    && $device_type != 'web'
                    && $device_id != 'web'
                    && $push_key == 'web'
                ) {
                    $userAppInfo->push_alarm = 'N';
                    $userAppInfo->notice_alarm = 'N';
                    $userAppInfo->album_alarm = 'N';
                    $userAppInfo->advice_alarm = 'N';
                    $userAppInfo->attendance_alarm = 'N';
                    $userAppInfo->adu_info_alarm = 'N';
                    $userAppInfo->event_alarm = 'N';
                }  else {
                    $userAppInfo->push_alarm = 'Y';
                    $userAppInfo->notice_alarm = 'Y';
                    $userAppInfo->album_alarm = 'Y';
                    $userAppInfo->advice_alarm = 'Y';
                    $userAppInfo->attendance_alarm = 'Y';
                    $userAppInfo->adu_info_alarm = 'Y';
                    $userAppInfo->event_alarm = 'Y';
                }
                $userAppInfo->device_kind = $device_kind;
                $userAppInfo->device_type = $device_type;
                $userAppInfo->device_id = $device_id;
                $userAppInfo->push_key = $push_key;
                $userAppInfo->save();
            } else {
                if ($device_kind != 'web'
                    && $device_type != 'web'
                    && $device_id != 'web'
                    && $push_key == 'web'
                ) {
                    $payload = [
                        'user_id' => $user->idx,
                        'device_kind' => $device_kind,
                        'device_type' => $device_type,
                        'device_id' => $device_id,
                        'push_key' => $push_key,
                        'push_alarm' => 'N',
                        'notice_alarm' => 'N',
                        'album_alarm' => 'N',
                        'advice_alarm' => 'N',
                        'attendance_alarm' => 'N',
                        'adu_info_alarm' => 'N',
                        'event_alarm' => 'N',
                        'wifi' => 'N'
                    ];
                }

                $userAppInfo = new UserAppInfo($payload);
                $userAppInfo->save();
            }
        }

        $result = Arr::add($result, 'push_alarm', $userAppInfo->push_alarm);
        $result = Arr::add($result, 'notice_alarm', $userAppInfo->notice_alarm);
        $result = Arr::add($result, 'album_alarm', $userAppInfo->album_alarm);
        $result = Arr::add($result, 'advice_alarm', $userAppInfo->advice_alarm);
        $result = Arr::add($result, 'attendance_alarm', $userAppInfo->attendance_alarm);
        $result = Arr::add($result, 'adu_info_alarm', $userAppInfo->adu_info_alarm);
        $result = Arr::add($result, 'event_alarm', $userAppInfo->event_alarm);
        $result = Arr::add($result, 'wifi', $userAppInfo->wifi);
    }

    //로그아웃
    public function logout(Request $request)
    {
        $result = [];

        $user_id = $request->input('user');
        $device_kind = $request->input('device_kind');
        $device_type = $request->input('device_type');
        $device_id = $request->input('device_id');
        $push_key = $request->input('push_key');
//        \App::make('helper')->vardump($request->input('user'));
//        exit;

        $user = RaonMember::find($user_id);


        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if ($user->mtype === 's') {
            $children_search_mobilephone = str_replace('-', '', $user->mobilephone);

//            $children_rs = RaonMember::where(DB::raw("REPLACE(`mobilephone`, '-', '')"), $children_search_mobilephone)
            $children_rs = RaonMember::where('mobilephone', $user->mobilephone)
                ->where('mtype', 's')
                ->whereIn('s_status', array('W', 'Y'))
//                ->orderBy('s_status', 'desc')
                ->get();

            if ($children_rs->count() == 0) {
                $result = Arr::add($result, 'result', 'fail');
                $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
                return response()->json($result);
            }

            foreach ($children_rs as $children_index => $children_row) {
                UserAppInfo::where('user_id', $children_row->idx)
                    ->where('device_kind', $device_kind)
                    ->where('device_type', $device_type)
                    ->where('device_id', $device_id)
                    ->update(
                        [
                            'device_kind' => null,
                            'device_type' => null,
                            'device_id' => null,
                            'push_key' => null
                        ]
                    );
            } // foreach End
        } else {
            UserAppInfo::where('user_id', $user->idx)
                ->where('device_kind', $device_kind)
                ->where('device_type', $device_type)
                ->where('device_id', $device_id)
                ->update(
                    [
                        'device_kind' => null,
                        'device_type' => null,
                        'device_id' => null,
                        'push_key' => null
                    ]
                );

        }
        $result = Arr::add($result, 'result', 'success');

        return response()->json($result);
    }

    public function resetPassword(Request $request)
    {
        $result = array();
        $login_id = $request->input('login_id') ?? '';
        $phone = $request->input('phone') ?? '';

        if ($login_id == '') {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '아이디를 입력해주세요.');
            return response()->json($result);
        }

        if ($phone == '') {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '휴대폰번호를 입력해주세요.');
            return response()->json($result);
        }

        if ($phone) {
            $phone = str_replace('-', '', $phone);
        }

        $sms_phone = $request->input('sms_phone');
        //$reset_password = random_int(1000, 9999);
        $reset_password = 1111;

        $user = RaonMember::selectRaw('*, PASSWORD(?) as input_pw', [$reset_password])->where('id', $login_id)->whereRaw("replace(mobilephone, '-', '') = ?", $phone)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        $sms_phone = $sms_phone ? $sms_phone : $phone;

        $msg = "아소비 임시비밀번호입니다.\n로그인 후 비밀번호를 변경해주세요.\n임시비밀번호: [{$reset_password}]";
        $bool = \App::make('helper')->sendSms($sms_phone, $msg);

        if ($user->mtype == 's') {
            $rs = RaonMember::where(DB::raw("REPLACE(`mobilephone`, '-', '')"), $phone)
                ->where('mtype', 's')
                ->whereIn('s_status', array('W', 'Y'))
                ->orderBy('s_status', 'desc')
                ->get();

            if ($rs) {
                foreach ($rs as $index => $row) {
                    $row->pw = $user->input_pw;
                    $row->save();
                }
            }
        } else {
            $user->pw = $user->input_pw;
            $user->save();
        }

//        4275

        $result = Arr::add($result, 'result', 'success');
//        $result = Arr::add($result, 'error', $reset_password);
        $result = Arr::add($result, 'error', '사용자 정보가 초기화 되었습니다.');
        $result = Arr::add($result, 'password', $reset_password);
        $result = Arr::add($result, 'sms_bool', $bool);

        return response()->json($result);
    }

    //회원정보수정
    public function update(Request $request, $type, $kind=null)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($type, ['push','picture','password','wifi','myInfo'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            $result = Arr::add($result, 'input', 'type');
            return response()->json($result);
        }

        $method = $type.'Update';

        $this->$method($user, $kind, $result, $request);

        return response()->json($result);
    }

    public function myInfoUpdate(&$user, &$kind, &$result, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'birth' => 'required|date',
            'sex' => ['required', new Sex],
            'parent_name' => 'required',
            'parent_contact' => ['required', new Phone],
            'adress' => 'required',
            'adress_desc' => 'required',
            'cognitive_pathway' => 'required',
            'marketing' => ['required', new YN],
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => json_decode($validator->errors()->__toString(), true)
            ]);
        }

        $userMem = RaonMember::where('idx', $user->idx)->first();
        if (empty($userMem)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 유저정보가 없습니다.(1)');
            return response()->json($result);
//            $userMem = new UserMemberDetail();
//            $userMem->user_id = $user->id;
        }

        $userDetail = RaonMember::where('idx', $user->idx)->first();
        if (empty($userDetail)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 유저정보가 없습니다.(2)');
            return response()->json($result);
//            $userDetail = new UserDetail();
//            $userDetail->user_id = $user->id;
        }

        $name = $request->input('name');
        $birth = $request->input('birth');
        $sex = $request->input('sex');
        $parent_name = $request->input('parent_name');
        $parent_contact = $request->input('parent_contact');
        $adress = $request->input('adress');
        $adress_desc = $request->input('adress_desc');
        $cognitive_pathway = $request->input('cognitive_pathway');
        $marketing = $request->input('marketing');

        $phone = \App::make('helper')->hypenPhone($parent_contact);

        //변경하려는 같은 폰이 있을 경우에 에러
        if (str_replace('-', '', $phone) != str_replace('-', '', $user->mobilephone)) {
            $isUser = RaonMember::where(DB::raw("REPLACE(`mobilephone`, '-', '')"), str_replace('-', '', $phone))->get();
            if ($isUser->count() > 0) {
                $result = Arr::add($result, 'result', 'fail');
                $result = Arr::add($result, 'error', '변경하려고 하는 휴대폰 번호가 이미 등록되어 있습니다.');
                return response()->json($result);
            }
        }

        //학부모일 경우 자녀의 휴대폰 번호를 변경한다.
        if ($user->mtype == 's') {
            $rs = RaonMember::where(DB::raw("REPLACE(`mobilephone`, '-', '')"), str_replace('-', '', $user->mobilephone))
                ->where('mtype', 's')
//                ->whereIn('status', array('W', 'Y'))
                ->orderBy('s_status', 'desc')
                ->get();

            if ($rs) {
                foreach ($rs as $index => $row) {
                    $row->mobilephone = $phone;
                    $row->save();
                }
            }
            $user->name = $name;
            $user->save();
        } else {
            $user->name = $name;
            $user->mobilephone = $phone;
            $user->save();
        }

        $userMem->parent_name = $parent_name;
        $userMem->parent_contact = $parent_contact;
        $userMem->cognitive_path = $cognitive_pathway;
        $userMem->save();

        $userDetail->sex = $sex;
        $userDetail->birthday = $birth;
        $userDetail->address1 = $adress;
        $userDetail->address2 = $adress_desc;
        $userDetail->mailling = $marketing;
        if ($marketing == 'Y') {
            $userDetail->mailling_date = date('Y-m-d H:i:s');
        } else {
            $userDetail->mailling_date = date('Y-m-d H:i:s');
//            $userDetail->marketing_consented_at = "0000-00-00 00:00:00";
        }
        $userDetail->save();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'login_id', ($user->mtype == 's') ? str_replace('-', '', $phone) : $user->user_id);
        $result = Arr::add($result, 'error', '수정 되었습니다.');
    }

    public function wifiUpdate(&$user, &$kind, &$result, Request $request)
    {
        $device_id = $request->input('device_id');
        $set = $request->input('set');

        $user_info = UserAppInfo::where('user_id', $user->idx)
            ->where('device_id', $device_id)
            ->first();
        if (empty($user_info)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 유저정보가 없습니다.');
            return response()->json($result);
        }

        $user_info->wifi = $set;
        $user_info->save();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '수정 되었습니다.');
    }

    //회원정보수정 알림 업데이트
    public function pushUpdate(&$user, &$kind, &$result, Request $request)
    {
        $device_id = $request->input('device_id');
        $push = $request->input('push');

        if (!$push) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            $result = Arr::add($result, 'input', 'push');
            return response()->json($result);
        }

        if (!in_array($kind, ['push','notice','album','advice','attendance','adu_info','event'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            $result = Arr::add($result, 'input', 'kind');
            return response()->json($result);
        }


//        $phone = str_replace('-', '', $user->mobilephone);
//
//        $rs = RaonMember::where(DB::raw("REPLACE(`mobilephone`, '-', '')"), $phone)
//            ->where('mtype', 's')
//            ->pluck('idx')
//            ->toArray();
//
//        $user_info = UserAppInfo::whereIn('user_id', $rs)
//            ->where('device_id', $device_id)
//            ->get();


        $user_info = UserAppInfo::where('user_id', $user->idx)
            ->where('device_id', $device_id)
            ->first();

        if (empty($user_info)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 유저정보가 없습니다.');
            return response()->json($result);
        }

        $push_column = $kind."_alarm";
        $user_info->$push_column = $push;
        $user_info->save();

        // @20210928 한명이상 입회한 학부모 경우 나머지 학생도 출석알림 동기화
//        if ($kind === 'attendance') {
        if ($user->mobilephone) {
            $child_users = RaonMember::where('mobilephone', $user->mobilephone)
                ->where('mtype', 's')
                ->whereNotIn('id', array($user->id))
                ->get();

            if ($child_users->count()) {
                $child_users->map(function($child_user) use($kind, $device_id, $push) {
                    $child_user_info = UserAppInfo::where('user_id', $child_user->idx)
                        ->where('device_id', $device_id)
                        ->first();

                    if ($child_user_info) {
//                            $child_user_info->attendance_alarm = $push;
                        $push_column = $kind."_alarm";
                        $child_user_info->$push_column = $push;
                        $child_user_info->save();
                    }
                });
            }
        }
//        }

        $result = Arr::add($result, 'result', 'success');
//        $result = Arr::add($result, 'push_column', $push_column);
//        $result = Arr::add($result, 'push', $push);
//        $result = Arr::add($result, 'user_info', $user_info->__toString() ?? "");
        $result = Arr::add($result, 'error', '수정 되었습니다.');

    }

    //프로필 이미지 업데이트
    public function pictureUpdate(&$user, &$kind, &$result, Request $request)
    {
        $file = $request->file('picture');

        $validator = Validator::make($request->all(), [
            'picture' => [new UploadFile],
        ]);

        if($validator->fails()){
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '업로드 하려는 파일은 동영상, 이미지만 가능하고 이미지는 10Mb이하, 동영상은 100Mb 이하로만 가능합니다.');
            return response()->json($result);
        }

        if ($file && $user) {
            $userMemberDetail = RaonMember::where('idx', $user->idx)->first();
            $profile_image = $userMemberDetail->user_picture ?? '';

            $file = \App::make('helper')->rotateImage($file);
            $file_path = \App::make('helper')->putResizeS3(UserAppInfo::FILE_DIR, $file);
            $userMemberDetail->user_picture = $file_path;
            $userMemberDetail->save();

            $rs = false;
            // 기존 이미지 삭제
            if ($profile_image) $rs = \App::make('helper')->deleteImage($profile_image);

            $result = Arr::add($result, 'result', 'success');
            $result = Arr::add($result, 'error', '수정 되었습니다.');
            $result = Arr::add($result, "new_picture", $file_path ? \App::make('helper')->getImage($file_path) : '');
            $result = Arr::add($result, "old_picture", ($rs === true) ? '삭제완료' : '삭제실패');
        }
    }

    //비밀번호 변경
    public function passwordUpdate(&$user, &$kind, &$result, Request $request)
    {
        $password = $request->input('password');

        $user = RaonMember::selectRaw('*, PASSWORD(?) as input_pw', [$password])->whereRaw("idx = ?", $user->idx)->first();

        if ($user) {
            if ($user->mtype == 's') {
                $phone = str_replace('-', '', $user->mobilephone);
                $rs = RaonMember::where(DB::raw("REPLACE(`mobilephone`, '-', '')"), $phone)
                    ->where('mtype', 's')
                    ->whereIn('s_status', array('W', 'Y'))
                    ->orderBy('s_status', 'desc')
                    ->get();

                if ($rs) {
                    foreach ($rs as $index => $row) {
                        $row->pw = $user->input_pw;
                        $row->save();
                    }
                }
            } else {
                $user->pw = $user->input_pw;
                $user->save();
            }

            $result = Arr::add($result, 'result', 'success');
            $result = Arr::add($result, 'error', '수정 되었습니다.');
        }
    }

    public function tokenUpdate(Request $request)
    {
        $result = array();

        $device_kind = $request->input('device_kind') ?? '';
        $device_type = $request->input('device_type') ?? '';
        $device_id = $request->input('device_id') ?? '';
        $push_key = $request->input('push_key') ?? '';

        if ($push_key == "" || $device_kind == "" || $device_type == "" || $device_id == "") {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 접근입니다.');
            return response()->json($result);
        }

        $child = UserAppInfo::where('device_kind', $device_kind)
            ->where('device_type', $device_type)
            ->where('device_id', $device_id)->get();
        if ($child) {
            foreach ($child as $k => $l) {
                UserAppInfo::where('id', "=", $l->id)
                    ->update(
                        [
                            'push_key' => $push_key
                        ]
                    );
            }
        } else {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '디바이스 정보가 없습니다.');
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '토큰이 없데이트 되었습니다.');

        return response()->json($result);
    }

    public function loginForm()
    {
        return view('auth/loginForm');
    }

    public function loginAction(Request $request)
    {
        $auto_login = $request->input('auto_login');
//        \App::make('helper')->vardump([
//            $request->login_id,
//            $request->password,
//        ]);

        $validator = Validator::make($request->all(), [
            'login_id' => 'required',
            'password' => 'required',
        ]);

        $err_txt = \App::make('helper')->getValidError($validator);
        if ($err_txt != "") {
            \App::make('helper')->alert($err_txt);
//            \App::make('helper')->alert($err_txt, '/');
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
            $phpisMobile = true;
        } else {
            $phpisMobile = false;
        }

//        if ($_SERVER['REMOTE_ADDR'] === '221.148.221.127') {
//            \App::make('helper')->log('pushKeyError', ['message' => '푸시키를 받아오지 못해 앱을 완전히 종료 후 재로그인 부탁드리겠습니다.'], '', 'warning');
//        }

        if ($phpisMobile
            && $request->input('device_kind') == 'web'
            && $request->input('device_type') == 'web'
            && $request->input('device_id') == 'web'
            && $request->input('push_key') == 'web'
        ) {
            if (!in_array($_SERVER['REMOTE_ADDR'], ['221.148.221.127', '115.93.23.14'])) {
                \App::make('helper')->alert('푸시키를 받아오지 못하였습니다.\n 아소비 앱 삭제 > 휴대폰 재부팅 > 앱 재설치시 알림 권한을 허용해주세요.', '/');
            }
        }

        $ip = \App::make('helper')->getClientIp();
        $request->merge([
            'ip' => $ip,
        ]);
        $response = $this->login($request);
//        \App::make('helper')->vardump($response->original['user_type']);
//        exit;
        if ($response->original['result'] == 'success') {
            $arr = array_merge($response->original, [
                'user_type_ko' => \App::make('helper')->getUserType($response->original['user_type']),
                'device_kind' => $request->input('device_kind'),
                'device_type' => $request->input('device_type'),
                'device_id' => $request->input('device_id'),
                'push_key' => $request->input('push_key'),
                'ip' => $ip,
                'auto_login' => $auto_login,
            ]);
            session(['auth' => $arr]);
            return redirect('/');
        } else {
            $error = $response->original['error'] ?? '아이디 패스워드를 확인해주세요.';
            \App::make('helper')->alert($error);
//            \App::make('helper')->alert($error, '/');
        }
    }

    public function intra_loginAction(Request $request)
    {
        $auto_login = $request->input('auto_login');
//        \App::make('helper')->vardump([
//            $request->login_id,
//            $request->password,
//        ]);

        $validator = Validator::make($request->all(), [
            'login_id' => 'required',

        ]);

        $err_txt = \App::make('helper')->getValidError($validator);
        if ($err_txt != "") {
            \App::make('helper')->alert($err_txt);
        }

        $ip = \App::make('helper')->getClientIp();
        $request->merge([
            'ip' => $ip,
        ]);
        $response = $this->intra_login($request);
//        \App::make('helper')->vardump($response->original['user_type']);
//        exit;

        if ($response->original['result'] == 'success') {
            $arr = array_merge($response->original, [
                'user_type_ko' => \App::make('helper')->getUserType($response->original['user_type']),
                'device_kind' => $request->input('device_kind'),
                'device_type' => $request->input('device_type'),
                'device_id' => $request->input('device_id'),
                'push_key' => $request->input('push_key'),
                'ip' => $ip,
                'auto_login' => $auto_login,
            ]);
            session(['auth' => $arr]);
            return redirect('/');
        } else {
            $error = $response->original['error'] ?? '아이디 패스워드를 확인해주세요.';
            \App::make('helper')->alert($error);
        }
    }

    public function logoutAction(Request $request)
    {
//        $requestData = $request->all();
        $auth = session()->get('auth');
//        \App::make('helper')->vardump($auth);
//        $request->session()->flush();
        $request->merge([
            'user' => $auth['user_id'],
            'device_kind' => $auth['device_kind'],
            'device_type' => $auth['device_type'],
            'device_id' => $auth['device_id'],
            'push_key' => $auth['push_key'],
        ]);
//        $requestData = $request->all();
//        \App::make('helper')->vardump($requestData);
//        \App::make('helper')->vardump($auth);
//        exit;

        $user_id = $auth['user_id'] ?? "";
        $device_kind = $auth['device_kind'] ?? "";
        $device_type = $auth['device_type'] ?? "";
        $device_id = $auth['device_id'] ?? "";
        $push_key = $auth['push_key'] ?? "";
        $userAppInfo = UserAppInfo::where('user_id', $user_id)
            ->where('device_kind', $device_kind)
            ->where('device_type', $device_type)
            ->where('device_id', $device_id)
            ->first();

        $response = $this->logout($request);

        if ($response->original['result'] == 'success') {
            $request->session()->flush();
            Cookie::queue('auto_login', '', 0);
            if ($auth['device_kind'] == "web") {
                return redirect('/');
            } else {
                if ($userAppInfo) {
                    $push_key = $userAppInfo->push_key;
                }

                $h = "/auth/login?fcmToken={$push_key}&userId={$user_id}&deviceId={$device_id}&os={$device_kind}&deviceType={$device_type}";
                return redirect($h);
            }
        } else {
            \App::make('helper')->alert("잘못된 접근입니다.");
        }
    }

}
