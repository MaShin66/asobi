<?php

namespace App\Http\Controllers;

//use App\Exports\UsersExport;
use App\User;
use App\UserDetail;
use App\UserMemberDetail;
use App\ShopCategory;
use App\UserAppInfo;
//use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
//use Maatwebsite\Excel\Facades\Excel;
use Validator;
use App\Rules\YN;
use App\Rules\Sex;
use App\Rules\Phone;

class UserController extends Controller
{
    public function userAdd(Request $request)
    {
        $result = [];

        $validator = Validator::make($request->all(), [
            'center_id' => 'required',
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

        $center_id = $request->input('center_id');
        $name = $request->input('name');
        $birth = $request->input('birth');
        $sex = $request->input('sex');
        $parent_name = $request->input('parent_name');
        $parent_contact = $request->input('parent_contact');
        $adress = $request->input('adress');
        $adress_desc = $request->input('adress_desc');
        $cognitive_pathway = $request->input('cognitive_pathway');
        $marketing = $request->input('marketing');

        //교육원 있는지 확인.
        $center = User::where('user_type', 'm')->where('status', 'Y')->whereId($center_id)->first();
        if (empty($center)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '교육원이 올바르지 않습니다.');
            return response()->json($result);
        }

        $phone = \App::make('helper')->hypenPhone($parent_contact);

        $user = new User();
        $user->name = $name;
        $user->phone = $phone;
        $user->user_type = 's';
        $user->branch_id = $center->branch_id;
        $user->center_id = $center->center_id;
        // todo: 입회신청 api가 완료되면 N으로 변경해야할듯.
        $user->status = 'Y';
//        $user->login_time = date('Y-m-d H:i:s');
        $user->user_id = "st_".time();
        $user->save();

        $user->user_id = 'st_'.$user->id;
        $user->save();

        $userMem = new UserMemberDetail();
        $userMem->user_id = $user->id;
        $userMem->parent_name = $parent_name;
        $userMem->parent_contact = $parent_contact;
        $userMem->cognitive_pathway = $cognitive_pathway;
        $userMem->save();

        $userDetail = new UserDetail();
        $userDetail->user_id = $user->id;
        $userDetail->gender = $sex;
        $userDetail->birthday = $birth;
        $userDetail->address = $adress;
        $userDetail->address_detail = $adress_desc;
        $userDetail->marketing_consent = $marketing;
        if ($marketing == 'Y') {
            $userDetail->marketing_consented_at = date('Y-m-d H:i:s');
        }
        $userDetail->save();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '신청 완료 되었습니다.');
        $result = Arr::add($result, 'id', $user->id);

        return response()->json($result);
    }

    public function centerAll()
    {
        $result = array();

        $rs = User::where('user_type', 'm')->where('status', 'Y')->orderBy('nickname')->get();
        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            foreach ($rs as $index => $row) {
                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.name", $row->nickname);
            }
        }

        return response()->json($result);
    }

    public function center(Request $request)
    {
        $result = array();

        $user_id = $request->input('user');
        $user = User::whereId($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if ($user->user_type != 'h') {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $rs = User::where('user_type', 'm')->where('status', 'Y')->where('branch_id', $user->id)->orderBy('nickname')->get();
        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            foreach ($rs as $index => $row) {
                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.name", $row->nickname);
            }
        }

        return response()->json($result);
    }

    public function branch(Request $request)
    {
        $result = array();

        $user_id = $request->input('user');
        $user = User::whereId($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if ($user->user_id != 'admin') {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $rs = User::where('user_type', 'h')->where('status', 'Y')->orderBy('nickname')->get();
        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            foreach ($rs as $index => $row) {
                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.name", $row->nickname);
            }
        }

        return response()->json($result);
    }

    public function student(Request $request)
    {
        $result = array();

        $user_id = $request->input('user');
        $user = User::whereId($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if ($user->user_type != 'm') {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $rs = User::where('center_id', $user->id)
            ->where('user_type', 's')
            ->where('status', 'Y')
            ->orderBy('name', 'asc')
            ->get();
        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            foreach ($rs as $index => $row) {
                $userMemberDetail = UserMemberDetail::where('user_id', $row->id)->first();
                $profile_image = $userMemberDetail->profile_image ?? '';

                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.name", $row->name);
                $result = Arr::add($result, "list.{$index}.profile_image", $profile_image ? \App::make('helper')->getImage($profile_image) : null);
            }
        }

        return response()->json($result);
    }

    public function children(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = User::whereId($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->user_type, ['s', 'm'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        if ($user->user_type == 's') {
            $phone = str_replace('-', '', $user->phone);

            $rs = User::where(DB::raw("REPLACE(`phone`, '-', '')"), $phone)
                ->where('user_type', 's')
                ->whereIn('status', array('W', 'Y'))
                ->orderBy('status', 'desc')
                ->get();

            $result = Arr::add($result, 'result', 'success');
            $result = Arr::add($result, 'count', $rs->count());
            if ($rs) {
                foreach ($rs as $index => $row) {
                    $userMemberDetail = UserMemberDetail::where('user_id', $row->id)->first();
                    $profile_image = $userMemberDetail->profile_image ?? '';
                    $userDetail = UserDetail::where('user_id', $row->id)->first();

                    $birth_day = null;
                    if ($userDetail->birthday) {
                        $birth_str = str_replace("NaN","", $userDetail->birthday);
                        $birth_str = str_replace("-","", $birth_str);
                        if ($birth_str != "") {
                            $birth = Carbon::createFromFormat('Ymd', $birth_str);
                            $now = Carbon::now();
                            $diff = $birth->diff($now);
                            $birth_day = $birth->format('Y.m.d')."(".$diff->format('%y년 %m개월').")";
                        }
                    }

                    $result = Arr::add($result, "list.{$index}.id", $row->id);
                    $result = Arr::add($result, "list.{$index}.name", $row->name);
                    $result = Arr::add($result, "list.{$index}.profile_image", $profile_image ? \App::make('helper')->getImage($profile_image) : null);
                    $result = Arr::add($result, "list.{$index}.birthday", $birth_day);

                    $center = User::whereId($row->center_id)->first();
                    $result = Arr::add($result, "list.{$index}.branch_name", "아소비 공부방");
                    $result = Arr::add($result, "list.{$index}.center_name", $center ? $center->nickname : null);
                }
            }
        } else if ($user->user_type == 'm') {

            $rs = User::where('center_id', $user->id)
                ->where('user_type', 's')
                ->where('status', 'Y')
                ->orderBy('name', 'asc')
                ->get();

            $result = Arr::add($result, 'result', 'success');
            $result = Arr::add($result, 'count', $rs->count());

            if ($rs) {
                foreach ($rs as $index => $row) {
                    $userMemberDetail = UserMemberDetail::where('user_id', $row->id)->first();
                    $profile_image = $userMemberDetail->profile_image ?? '';
                    $userDetail = UserDetail::where('user_id', $row->id)->first();

                    $shopCategory = ShopCategory::where('depth', 1)->where('category_year', $userDetail->course_year)->first();

                    $birth_day = null;
                    if ($userDetail->birthday) {
                        $birth_str = str_replace("NaN","", $userDetail->birthday);
                        $birth_str = str_replace("-","", $birth_str);
                        if ($birth_str != "") {
                            $birth = Carbon::createFromFormat('Ymd', $birth_str);
                            $now = Carbon::now();
                            $diff = $birth->diff($now);
                            $birth_day = $birth->format('Y.m.d')."(".$diff->format('%y년 %m개월').")";
                        }
                    }

                    $result = Arr::add($result, "list.{$index}.id", $row->id);
                    $result = Arr::add($result, "list.{$index}.name", $row->name);
                    $result = Arr::add($result, "list.{$index}.profile_image", $profile_image ? \App::make('helper')->getImage($profile_image) : null);
                    $result = Arr::add($result, "list.{$index}.birthday", $birth_day);
                    $result = Arr::add($result, "list.{$index}.branch_name", $shopCategory ? $shopCategory->name : '');
                    $result = Arr::add($result, "list.{$index}.center_name", '');
                }
            }
        }

        return response()->json($result);
    }

    public function selectChild(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = User::whereId($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'user_id', $user->id);
        $result = Arr::add($result, 'user_name', $user->user_type == 's' ? $user->name : $user->nickname);
        if ($user->id == 1) {
            $result = Arr::add($result, 'user_type', 'admin');
        } else {
            $userMemberDetail = UserMemberDetail::where('user_id', $user->id)->first();
            $profile_image = $userMemberDetail->profile_image ?? '';
            $result = Arr::add($result, 'user_type', $user->user_type);
            $result = Arr::add($result, "user_picture", $profile_image ? \App::make('helper')->getImage($profile_image) : null);
            if ($user->user_type == 's') {
                $center = User::whereId($user->center_id)->first();
                $result = Arr::add($result, 'center_name', $center ? $center->nickname : null);
            }
        }
        $result = Arr::add($result, 'login_id', $user->id);

        return response()->json($result);
    }

    public function myInfo(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = User::whereId($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'login_id', $user->user_id);
        $result = Arr::add($result, 'user_name', $user->user_type == 's' ? $user->name : $user->nickname);
        $result = Arr::add($result, 'email', $user->email);
        $result = Arr::add($result, 'phone', $user->phone);

        $userMemberDetail = UserMemberDetail::where('user_id', $user->id)->first();
        $profile_image = $userMemberDetail->profile_image ?? '';
        $result = Arr::add($result, 'user_type', $user->user_type);
        $result = Arr::add($result, "user_picture", $profile_image ? \App::make('helper')->getImage($profile_image) : null);
        if ($user->user_type == 's') {
            $center = User::whereId($user->center_id)->first();
            $result = Arr::add($result, 'center_name', $center ? $center->nickname : null);
        }

        $userDetail = UserDetail::where('user_id', $user->id)->first();
        $result = Arr::add($result, 'gender', $userDetail->gender);
        $result = Arr::add($result, 'birthday', $userDetail->birthday);
        $result = Arr::add($result, 'address', $userDetail->address);
        $result = Arr::add($result, 'address_detail', $userDetail->address_detail);
        $result = Arr::add($result, 'marketing_consent', $userDetail->marketing_consent);
        $result = Arr::add($result, 'marketing_consented_at', $userDetail->marketing_consented_at);
        $result = Arr::add($result, 'parent_name', $userMemberDetail->parent_name??null);
        $result = Arr::add($result, 'cognitive_pathway', $userMemberDetail->cognitive_pathway??null);

        return response()->json($result);
    }

    public function alramInfo(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = User::whereId($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        $device_id = $request->input('device_id')?? '';
        $userAppInfo = UserAppInfo::where('user_id','=',$user_id)->where('device_id',$device_id)->first();
        if (empty($userAppInfo)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '로그인 정보가 없습니다.');
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'advice_alarm', $userAppInfo->advice_alarm ?? 'N');
        $result = Arr::add($result, 'album_alarm', $userAppInfo->album_alarm ?? 'N');
        $result = Arr::add($result, 'attendance_alarm', $userAppInfo->attendance_alarm ?? 'N');
        $result = Arr::add($result, 'notice_alarm', $userAppInfo->notice_alarm ?? 'N');
        $result = Arr::add($result, 'adu_info_alarm', $userAppInfo->adu_info_alarm ?? 'N');
        $result = Arr::add($result, 'event_alarm', $userAppInfo->event_alarm ?? 'N');
        $result = Arr::add($result, 'wifi', $userAppInfo->wifi ?? 'N');

        return response()->json($result);
    }

}
