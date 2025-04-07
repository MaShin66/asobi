<?php

namespace App\Http\Controllers;

use App\AdviceNote;
use App\Album;
use App\Attendance;
use App\Http\Controllers\AppMainController;
use App\Jobs\BatchPush;
use App\Notice;
use App\Models\RaonMember;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class AttendanceController extends Controller
{
    public function student(Request $request, AppMainController $appMainController)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->mtype, ['m'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $now = Carbon::now();
        $year = $request->input('year')  ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d',$request->input('month')) : $now->format('m');
        $day = $request->input('day') ? sprintf('%02d',$request->input('day')) : null;

        $rs = RaonMember::where('midx', $user->idx)
            ->where('mtype', 's')
            ->where('s_status', 'Y')
            ->orderBy('name', 'asc')
            ->get();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            if (!$day) {
                $req = Request();
                $req->merge([
                    'user' => $user->idx,
                    'year' => $year,
                    'month' => $month,
                ]);
                $rep = $appMainController->calendar($req);
            }
            foreach ($rs as $index => $row) {
                $userMemberDetail = RaonMember::where('idx', $row->idx)->first();
                $profile_image = $userMemberDetail->user_picture ?? '';

                $result = Arr::add($result, "list.{$index}.id", $row->idx);
                $result = Arr::add($result, "list.{$index}.name", $row->name);
                $result = Arr::add($result, "list.{$index}.profile_image", $profile_image ? \App::make('helper')->getImage($profile_image) : null);
                if ($day) {
                    $attendance = Attendance::where('sidx', $row->idx)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->where('day', $day)
                        ->first();

                    $in = $out = 0;
                    if ($attendance) {
                        $in = $attendance->in;
                        $out = $attendance->out;
                    }

                    $result = Arr::add($result, "list.{$index}.attendance_in", $in);
                    $result = Arr::add($result, "list.{$index}.attendance_out", $out);
                } else {
                    $attendance = Attendance::where('sidx', $row->idx)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->where('in', '1')
                        ->get();

                    $result = Arr::add($result, "list.{$index}.attendance_cont", $attendance->count());
                    $result = Arr::add($result, "list.{$index}.attendance_all_cont", $rep->original['count']??0);
                }
            }
        }

        return response()->json($result);
    }

    public function index(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        $now = Carbon::now();
        $year = $request->input('year')  ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d',$request->input('month')) : $now->format('m');

        if (!in_array($user->mtype, ['s'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $attendance_rs = Attendance::where('sidx', $user->idx)->where('year', $year)->where('month', $month)->where(function ($q){
            $q->where('in', '1')
                ->orWhere('out','1');
        })->get();
        $attendance_count = 0;
        $advice_rs = AdviceNote::where('sidx', $user->idx)->where('year', $year)->where('month', $month)->where('type', AdviceNote::ADVICE_TYPE)->get();
        $letter_rs = AdviceNote::where('sidx', $user->idx)->where('year', $year)->where('month', $month)->where('type', AdviceNote::LETTER_TYPE)->get();
        $notice_rs = Notice::where('status', 'Y')
            ->whereIn('midx', [$user->midx, 0])
            ->where('view_type', 'like', "%" . json_encode($user->mtype) . "%")
            ->where('year', $year)
            ->where('month', $month)
            ->orderByDesc('created_at')
            ->get();

        $album_rs = Album::where('status', 'Y')
//            ->where('sidx', 'like', "%" . json_encode($user->idx) . "%")
            ->whereJsonContains('sidx', json_encode($user->idx))
            ->where('year', $year)
            ->where('month', $month)
            ->orderByDesc('created_at')
            ->get();

        $result = Arr::add($result, 'result', 'success');

        $in = $out = [];
        if ($attendance_rs) {
            foreach ($attendance_rs as $index => $row) {
                $this_date = Carbon::create($row->year, $row->month, $row->day)->format(Attendance::DATE_FORMAT);
                if ($row->in == "1") {
                    $in[] = $this_date;
                    $attendance_count++;
                }
                if ($row->out == "1") {
                    $out[] = $this_date;
                }
            }
        }

        $result = Arr::add($result, 'attendance_count', $attendance_count);
        $result = Arr::add($result, "attendance_in", $in);
        $result = Arr::add($result, "attendance_out", $out);


        $date_info = [];
        if ($advice_rs) {
            $advice_cnt_arr = [];
            foreach ($advice_rs as $index => $row) {
                $this_date = Carbon::create($row->year, $row->month, $row->day)->format("Y.m.d");
                if (! isset($advice_cnt_arr[$this_date])) {
                    $advice_cnt_arr[$this_date] = 0;
                }
                $date_info[$this_date]['adviceId'] = '/advice/' . $user->idx . '/note/view/' . $row->id;
                $date_info[$this_date]['advice'] = ++$advice_cnt_arr[$this_date];
//                $date_info['advice'][$this_date] = ++$advice_cnt_arr[$this_date];
            }
        }

        if ($letter_rs) {
            $letter_cnt_arr = [];
            foreach ($letter_rs as $index => $row) {
                $this_date = Carbon::create($row->year, $row->month, $row->day)->format("Y.m.d");
                if (! isset($letter_cnt_arr[$this_date])) {
                    $letter_cnt_arr[$this_date] = 0;
                }
                $date_info[$this_date]['letterId'] = '/advice/' . $user->idx . '/letter/view/' . $row->id;
                $date_info[$this_date]['letter'] = ++$letter_cnt_arr[$this_date];
//                $date_info['letter'][$this_date] = ++$letter_cnt_arr[$this_date];
            }
        }

        if ($notice_rs) {
            $notice_cnt_arr = [];
            foreach ($notice_rs as $index => $row) {
                $this_date = Carbon::create($row->year, $row->month, $row->day)->format("Y.m.d");
                if (! isset($notice_cnt_arr[$this_date])) {
                    $notice_cnt_arr[$this_date] = 0;
                }
                $date_info[$this_date]['noticeId'] = '/notice/view/' . $row->id;
                $date_info[$this_date]['notice'] = ++$notice_cnt_arr[$this_date];
//                $date_info['notice'][$this_date] = ++$notice_cnt_arr[$this_date];
            }
        }

        if ($album_rs) {
            $album_cnt_arr = [];
            foreach ($album_rs as $index => $row) {
                $this_date = Carbon::create($row->year, $row->month, $row->day)->format("Y.m.d");
                if (! isset($album_cnt_arr[$this_date])) {
                    $album_cnt_arr[$this_date] = 0;
                }
                $date_info[$this_date]['albumId'] = '/album/view/' . $row->id;
                $date_info[$this_date]['album'] = ++$album_cnt_arr[$this_date];
//                $date_info['album'][$this_date] = ++$album_cnt_arr[$this_date];
            }
        }

        if (count($date_info) > 0) {
            $sort_keys = array_keys($date_info);
            $sort_keys_proc = [];
            foreach ($sort_keys as $l) {
                $dt = explode('.',$l);
                $sort_keys_proc[] = strtotime($dt[0]."-".$dt[1]."-".$dt[2]);
            }

            array_multisort($sort_keys_proc,SORT_DESC, $date_info);

//            foreach ($date_info as $key => $value) {
//                foreach ($value as $key2 => $value2) {
//
//                    if ($key2 === 'advice') {
//                        $arrayValue = array(
//                            $key => array(
//                                'advice' => $value2
//                            )
//                        );
//
//                        $date_info = array($arrayValue) + $date_info;
//
//                        unset($date_info[$key]['advice']);
//                    }
//                }
//            }
        }

//        dd($date_info);

        $result = Arr::add($result, "date_info", $date_info);

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->mtype, ['m'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $student_id = $request->input('student');
        $student = RaonMember::whereIdx($student_id)->first();
        if (empty($student)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '학생 정보가 없습니다.');
            return response()->json($result);
        }
        if ($student->midx != $user->idx || $student->mtype != 's') {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '학생의 권한이 없습니다.');
            return response()->json($result);
        }

        $type = $request->input('type');
        if ($type == "" || !in_array($type, ['in','out'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '타입은 in, out만 가능합니다.');
            return response()->json($result);
        }

        $check = $request->input('check');
        if (!in_array($check, ['1','0'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '체크값은 1, 0만 가능합니다.');
            return response()->json($result);
        }

        $now = Carbon::now();
        $year = $request->input('year')  ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d',$request->input('month')) : $now->format('m');
        $day = $request->input('day') ? sprintf('%02d',$request->input('day')) : $now->format('d');

        //기존 출석부 데이터 있는지 확인.
        $attendance = Attendance::where('sidx', $student_id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('day', $day)
            ->first();

        if (empty($attendance)) {
            $payload = [
                'hidx' => $user->midx,
                'midx' => $user->idx,
                'sidx' => $student_id,
                'year' => $year,
                'month' => $month,
                'day' => $day
            ];
            $payload[$type] = $check;
            if ($check == '1') {
                $payload[$type."_at"] = date('Y-m-d H:i:s');
            } else {
                $payload[$type."_at"] = null;
            }

            $attendance = new Attendance($payload);
            $attendance->save();
        } else {
            if ($attendance->$type == $check) {
                $result = Arr::add($result, 'result', 'fail');
                $err = '기존 ';
                $err .= ($type == 'in') ? '등원' : '하원';
                $err .= '상태값과 같아 변경되지 않았습니다.';
                $result = Arr::add($result, 'error', $err);
                return response()->json($result);
            }

            $attendance->$type = $check;
            $type_at = $type."_at";
            if ($check == '1') {
                $attendance->$type_at = date('Y-m-d H:i:s');
            } else {
                $attendance->$type_at = null;
            }
            $attendance->update();
        }

        $attendance->refresh();
//        $push = new PushMessageController('attendance', $attendance->id, ['type' => $type, 'check' => $check]);
//        $push->push();

        BatchPush::dispatch(['type' => 'attendance', 'type_id' => $attendance->id, 'param' => ['type' => $type, 'check' => $check]]);

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '저장 되었습니다.');
        $result = Arr::add($result, 'id', $attendance->id);

        return response()->json($result);
    }

    public function destroyMany(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->mtype, ['m'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $student = $request->input('student');

        $now = Carbon::now();
        $year = $request->input('year')  ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d',$request->input('month')) : $now->format('m');
        $day = $request->input('day') ? sprintf('%02d',$request->input('day')) : $now->format('d');

        if (!is_array($student) || count($student) == 0) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '삭제 할 회원을 선택해주세요.');
            return response()->json($result);
        }

        foreach ($student as $student_id) {
            $attendance = Attendance::where('sidx', $student_id)
                ->where('year', $year)
                ->where('month', $month)
                ->where('day', $day)
                ->first();

            if ($attendance) {
                $attendance->delete();
            } else {
                $result = Arr::add($result, 'result', 'fail');
                $result = Arr::add($result, 'error', '잘못된 요청입니다.');
                $result = Arr::add($result, 'student_id', $student_id);
            }
        } // foreach End

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '삭제 되었습니다.');

        return response()->json($result);
    }

    public function destroy(Request $request, $attendance_id)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->mtype, ['m'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $attendance = Attendance::where('midx', $user->idx)->where('id', $attendance_id)->first();

        if (empty($attendance)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            return response()->json($result);
        }

        $attendance->delete();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '삭제 되었습니다.');

        return response()->json($result);
    }

    public function attend(Request $request)
    {
        $ym = $request->input('ym') ?? date('Y-m');
        $day = $request->input('day') ?? '';
        $year = $month = "";
        if ($ym != '') {
            $ymArr = explode('-', $ym);
            $year = $ymArr[0] ?? '';
            $month = $ymArr[1] ?? '';
        }

        $user = \App::make('helper')->getUsertId();
        $userType = \App::make('helper')->getUsertType();
        if (in_array($userType, ['a','h'])) {
            $user = session()->get('center');
        }

        $appMainController = new AppMainController();

//        dd($userType);

        if ($day == '' && $userType == 'm') {
            $req = Request::create('/api/isSchedule', 'GET', [
                'user' => $user,
                'year' => $year,
                'month' => $month,
                'day' => date('d'),
            ]);
            $res = $appMainController->isSchedule($req);
            $is = $res->original['is'] ?? false;
            if ($is) {
                return redirect('/attend?ym='.$year.'-'.$month.'&day='.date('d'));
            }
        }
        if ($day == 'all') $day = '';

        $req = Request::create('/api/attendance/student/list', 'GET', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ]);

        $res = $this->student($req, $appMainController);
        $list = $res->original['list'] ?? [];
        // \App::make('helper')->vardump($list);

        // 출석 가능 달력 리스트
        $calendarReq = Request::create('/calendar', 'GET', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
        ]);
        $calendarRes = $appMainController->calendar($calendarReq);
        $blueData = $calendarRes->original['list']['blue'] ?? [];
        $redData = $calendarRes->original['list']['red'] ?? [];


//        if ($_SERVER['REMOTE_ADDR'] === '221.148.221.127') {
//            echo '<xmp>';
//            var_dump($blueData);
//            echo '</xmp>';
//            dd($redData);
//        }


        $attendList = [];
        foreach ($blueData as $date) {
            array_push($attendList, intval(explode('-', $date)[2]));
        }
        foreach ($redData as $date) {
            array_push($attendList, intval(explode('-', $date)[2]));
        }
        sort($attendList);

        return view('attend/list', [
            'list' => $list,
            'ym' => $ym,
            'day' => $day,
            'attendList' => json_encode($attendList)
        ]);
    }

    public function attendAction(Request $request)
    {
        $ym = $request->input('ym') ?? date('Y-m');
        $day = $request->input('day') ?? '';
        $year = $month = "";
        if ($ym != '') {
            $ymArr = explode('-', $ym);
            $year = $ymArr[0] ?? '';
            $month = $ymArr[1] ?? '';
        }
        \App::make('helper')->vardump($day);

        if($request->input('day') == '') {
            return redirect()->to('attend?ym='.$ym.'&day=');
        }

        $typeId = $request->input('val') ?? '';
        $type = $id = $check = "";
        if($typeId != '') {
            $tiArr = explode('-', $typeId);
            $type = $tiArr[0] ?? '';
            $id = $tiArr[1] ?? '';
            $in = $request->input('in'.$id) ? 1 : 0;
            $out = $request->input('out'.$id) ? 1 : 0;

            if($type == 'in') {
                $check = $in;
            } else {
                $check = $out;
            }
        }

        $user = \App::make('helper')->getUsertId();

        $req = Request::create('/api/attendance/write', 'GET', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'student' => $id,
            'type' => $type,
            'check' => $check,
        ]);
        $res = $this->store($req);
        // \App::make('helper')->vardump($res);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        return redirect()->to('attend?ym='.$ym.'&day='.$day);
    }

    public function attendCalendar(Request $request)
    {
        return view('attend/calendar');
    }

    public function attendView(Request $request, $id)
    {
        $ajax = $request->input('ajax') ?? 0;
        $list = $request->input('list') ?? 0;
        $ym = $request->input('ym') ?? date('Y-m');

        // 알람 통해서 이동했는데 다른 자녀일 경우 홈으로
        if (session()->get('auth')['user_id'] != $id && $ajax === 0) {
            return redirect('/');
        }

        $year = $month = "";
        if ($ym != '') {
            $ymArr = explode('-', $ym);
            $year = $ymArr[0] ?? '';
            $month = $ymArr[1] ?? '';
        }

        // 출석 가능 달력 리스트
        $calendarReq = Request::create('/calendar', 'GET', [
            'user' => $id,
            'year' => $year,
            'month' => $month,
        ]);

        $appMainController = new AppMainController();
        $calendarRes = $appMainController->calendar($calendarReq);
        $attendAll = $calendarRes->original['count'] ?? 0;
        $blueData = $calendarRes->original['list']['blue'] ?? [];
        $redData = $calendarRes->original['list']['red'] ?? [];

        $blueList = $redList = [];
        foreach ($blueData as $date) {
            array_push($blueList, intval(explode('-', $date)[2]));
        }
        foreach ($redData as $date) {
            array_push($redList, intval(explode('-', $date)[2]));
        }

        // 출석부 데이터
        $attendReq = Request::create('/attendance/list', 'GET', [
            'user' => $id,
            'year' => $year,
            'month' => $month,
        ]);

        $attendRes = $this->index($attendReq);
        $attendCount = $attendRes->original['attendance_count'] ?? 0;
        $attendInData = $attendRes->original['attendance_in'] ?? [];
        $attendOutData = $attendRes->original['attendance_out'] ?? [];
        $infoList = $attendRes->original['date_info'] ?? [];
        $attendIn = $attendOut = $infoDates = [];
        foreach ($attendInData as $date) {
            array_push($attendIn, intval(explode('-', $date)[2]));
        }
        foreach ($attendOutData as $date) {
            array_push($attendOut, intval(explode('-', $date)[2]));
        }
        foreach ($infoList as $key => $date) {
            array_push($infoDates, intval(explode('.', $key)[2]));
        }

        // 학생 데이터
        $studentReq = Request::create('/main', 'GET', [
            'user' => $id,
        ]);
        $studentRes = $appMainController->index($studentReq);

        if ($ajax) {
            return response()->json([
                'calendar' => view('attend/view', [
                    'id' => $id,
                    'ym' => $ym,
                    'blueList' => $blueList,
                    'redList' => $redList,
                    'attendIn' => $attendIn,
                    'attendOut' => $attendOut,
                    'infoList' => $infoList,
                    'infoDates' => $infoDates,
                    'attendAll' => $attendAll,
                    'attendCount' => $attendCount,
                    'studentInfo' => $studentRes->original ?? [],
                    'ajax' => $ajax,
                    'list' => $list,
                ])->render()
            ]);
        } else {
            return view('attend/view', [
                'id' => $id,
                'ym' => $ym,
                'blueList' => $blueList,
                'redList' => $redList,
                'attendIn' => $attendIn,
                'attendOut' => $attendOut,
                'infoList' => $infoList,
                'infoDates' => $infoDates,
                'attendAll' => $attendAll,
                'attendCount' => $attendCount,
                'studentInfo' => $studentRes->original ?? [],
                'ajax' => $ajax,
                'list' => $list,
            ]);
        }

    }

}
