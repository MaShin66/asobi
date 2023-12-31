<?php

namespace App\Http\Controllers;

use App\AdviceComment;
use App\AdviceNote;
use App\Album;
use App\AlbumComment;
use App\CommonComment;
use App\AppNotice;
use App\Attendance;
use App\Event;
use App\Notice;
use App\PushLog;
use App\User;
use App\Services\FcmHandler;
use App\UserAppInfo;
use App\EducatonInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class PushMessageController extends Controller
{
    private $type;
    private $type_id;
    private $param;

    public function __construct($type, $type_id, $param=[])
    {
        $this->type = $type;
        $this->type_id = $type_id;
        $this->param = $param;
    }

    public function push()
    {
        $title = "아소비";
        $body = "알림";
        $arr_push = array();

        // @2022-01-26 2022-01-28일 까지만 적용
        $nowTime = time();
        $diffTime = strtotime('2022-01-28 23:59:59');

        if ($this->type == AdviceNote::ADVICE_TYPE || $this->type == AdviceNote::LETTER_TYPE)
        {
            $row = AdviceNote::find($this->type_id);

            if ($row) {
                $student = User::where('id', $row->sidx)->first();

                if ($student) {
                    $arr_push = UserAppInfo::where('user_id', $student->id)
                        ->where('advice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();

                    if ($row->type == AdviceNote::ADVICE_TYPE) {
                        $body = "{$student->name}의 알림장이 작성되었습니다.";
                    } else if ($row->type == AdviceNote::LETTER_TYPE) {
                        $body = "{$student->name}의 {$row->month}월 가정통신문이 작성되었습니다.";
                    }

                    if ($arr_push) {
                        $arr_push = array_unique($arr_push);
                        $arr_push = array_values($arr_push);

                        $pushLog = new PushLog([
                            'type' => $this->type,
                            'type_id' => $this->type_id,
                            'receivers' => json_encode($arr_push)
                        ]);

                        $pushLog->save();

                        if (env('APP_ENV') != 'dev') {
                            $handler = App::make(FcmHandler::class);
                            $handler->setReceivers($arr_push);
                            $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$this->type, 'id'=>$row->id, 'userId'=>$row->sidx]);
                            $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$this->type, 'id'=>$row->id,'userId'=>$row->sidx]);
                            $handler->sendMessage();
                        }
                    }
                }
            }
        }
        else if ($this->type == "adviceComment")
        {
            $row = AdviceComment::find($this->type_id);

            if ($row) {
                if ($row->writer_type == 'a') {
                    $arr_push = UserAppInfo::where('user_id', $row->sidx)
                        ->where('advice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();
                    $body = "본사 댓글이 작성되었습니다.";
                } else if ($row->writer_type == 'h') {
                    $arr_push = UserAppInfo::where('user_id', $row->sidx)
                        ->where('advice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();
                    $body = "지사 댓글이 작성되었습니다.";
                } else if ($row->writer_type == 'm') {
                    $arr_push = UserAppInfo::where('user_id', $row->sidx)
                        ->where('advice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();
                    $body = "교육원 댓글이 작성되었습니다.";
                } else if ($row->writer_type == 's') {
                    $student = User::where('id', $row->sidx)->first();

                    if($student){
                        $arr_push = UserAppInfo::where('user_id', $row->midx)->where('advice_alarm', 'Y')->whereNotNull('push_key')->pluck('push_key')->toArray();
                        $body = "{$student->name} 학부모 댓글이 작성되었습니다.";
                    }
                }

                if ($arr_push) {
                    $arr_push = array_unique($arr_push);
                    $arr_push = array_values($arr_push);

                    $pushLog = new PushLog([
                        'type' => $this->type,
                        'type_id' => $this->type_id,
                        'receivers' => json_encode($arr_push)
                    ]);

                    $pushLog->save();

                    if (env('APP_ENV') != 'dev') {
                        $handler = App::make(FcmHandler::class);
                        $handler->setReceivers($arr_push);
                        $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>'advice', 'id'=>$row->advice_note_id,'userId'=>$row->sidx]);
                        $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>'advice', 'id'=>$row->advice_note_id,'userId'=>$row->sidx]);
                        $handler->sendMessage();
                    }
                }
            }
        }
        else if ($this->type == "notice")
        {
            $row = Notice::find($this->type_id);

            if ($row) {
                if ($row->writer_type == 'm') {
                    if ($nowTime < $diffTime) {
                        $rs = User::where('center_id', $row->midx)
                            ->where('user_type', 's')
                            ->where(function($query) use($row) {
                                $query->where('status', 'Y')
                                    ->orWhereRaw("id IN (SELECT `student_id` FROM `logs` WHERE `center_id` = ? AND `log_type` = 'M2' AND date(`accepted_at`) between '2022-01-24' and '2022-01-28')", [$row->midx]);
                            })
                            ->pluck('id')
                            ->toArray();
                    } else {
                        $rs = User::where('center_id', $row->midx)
                            ->where('user_type', 's')
                            ->where('status', 'Y')
                            ->pluck('id')
                            ->toArray();
                    }

                    $arr_push = UserAppInfo::whereIn('user_id', $rs)
                        ->where('notice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();

                    $body = "[공지] {$row->title}";
                } else if ($row->writer_type == 'h') {
                    $rs = User::where('branch_id', $row->midx)
                        ->where('user_type', 'm')
                        ->where('status', 'Y')
                        ->pluck('id')
                        ->toArray();

                    $arr_push = UserAppInfo::whereIn('user_id', $rs)
                        ->where('notice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();
                    $body = "[공지] {$row->title}";
                } else if ($row->writer_type == 'a') {
                    if ($nowTime < $diffTime) {
                        $rs = User::where('center_id', 's')
                            ->where(function($query) use($row) {
                                $query->where('status', 'Y')
                                    ->orWhereRaw("idx IN (SELECT `student_id` FROM `logs` WHERE `log_type` = 'M2' AND date(`accepted_at`) between '2022-01-24' and '2022-01-28')");
                            })
                            ->pluck('idx')
                            ->toArray();
                    } else {
                        $rs = User::where('user_type', 's')
                            ->where('status', 'Y')
                            ->pluck('id')
                            ->toArray();
                    }

                    $arr_push = UserAppInfo::whereIn('user_id', $rs)
                        ->where('notice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();
                    $body = "[공지] {$row->title}";
                }

                if ($arr_push) {
                    $arr_push = array_unique($arr_push);
                    $arr_push = array_values($arr_push);

                    $pushLog = new PushLog([
                        'type' => $this->type,
                        'type_id' => $this->type_id,
                        'receivers' => json_encode($arr_push)
                    ]);

                    $pushLog->save();

                    if (env('APP_ENV') != 'dev') {
                        $handler = App::make(FcmHandler::class);
                        $handler->setReceivers($arr_push);
                        $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                        $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                        $handler->sendMessage();
                    }
                }
            }
        }
        else if ($this->type == "appNotice")
        {
            $row = AppNotice::find($this->type_id);

            if ($row) {
                if ($row->read_branch == 'Y') {
                    $branch_rs = User::where('user_type', 'h')
                        ->where('status', 'Y')
                        ->pluck('id')
                        ->toArray();

                    $center_rs = User::where('user_type', 'm')
                        ->where('status', 'Y')
                        ->pluck('id')
                        ->toArray();

                    $rs = array_merge($branch_rs, $center_rs);

                    $arr_push = UserAppInfo::whereIn('user_id', $rs)
                        ->where('notice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();
                } else if ($row->read_center == 'Y') {
                    $rs = User::where('branch_id', $row->hidx)
                        ->where('user_type', 'm')
                        ->where('status', 'Y')
                        ->pluck('id')
                        ->toArray();

                    $arr_push = UserAppInfo::whereIn('user_id', $rs)
                        ->where('notice_alarm', 'Y')
                        ->whereNotNull('push_key')
                        ->pluck('push_key')
                        ->toArray();
                }

                if ($arr_push) {
                    $arr_push = array_unique($arr_push);
                    $arr_push = array_values($arr_push);

                    $pushLog = new PushLog([
                        'type' => $this->type,
                        'type_id' => $this->type_id,
                        'receivers' => json_encode($arr_push)
                    ]);

                    $pushLog->save();

                    if (env('APP_ENV') != 'dev') {
                        $body = "[공지] {$row->title}";
                        $handler = App::make(FcmHandler::class);
                        $handler->setReceivers($arr_push);
                        $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                        $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                        $handler->sendMessage();
                    }
                }
            }
        }
        else if ($this->type == "album")
        {
            $row = Album::find($this->type_id);

            if ($row) {
                if ($row->sidx) {
                    $rs = json_decode($row->sidx);

                    if (is_array($rs) && count($rs)) {
                        $arr_push = UserAppInfo::whereIn('user_id', $rs)
                            ->where('album_alarm', 'Y')
                            ->whereNotNull('push_key')
                            ->pluck('push_key')
                            ->toArray();

                        if ($arr_push) {
                            $arr_push = array_unique($arr_push);
                            $arr_push = array_values($arr_push);

                            $pushLog = new PushLog([
                                'type' => $this->type,
                                'type_id' => $this->type_id,
                                'receivers' => json_encode($arr_push)
                            ]);

                            $pushLog->save();

                            $body = "교육원 앨범이 작성되었습니다.";

                            if (env('APP_ENV') != 'dev') {
                                $handler = App::make(FcmHandler::class);
                                $handler->setReceivers($arr_push);
                                $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                                $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                                $handler->sendMessage();
                            }
                        }
                    }
                }
            }
        }
        else if ($this->type == "albumComment")
        {
            $row = AlbumComment::find($this->type_id);

            if ($row) {
                $rs = json_decode($row->sidx);
                if ($row->writer_type == 'a') {
                    if (is_array($rs) && count($rs)) {
                        $arr_push = UserAppInfo::whereIn('user_id', $rs)
                            ->where('album_alarm', 'Y')
                            ->whereNotNull('push_key')
                            ->pluck('push_key')
                            ->toArray();
                    }

                    $body = "본사 댓글이 작성되었습니다.";
                } else if ($row->writer_type == 'h') {
                    if (is_array($rs) && count($rs)) {
                        $arr_push = UserAppInfo::whereIn('user_id', $rs)
                            ->where('album_alarm', 'Y')
                            ->whereNotNull('push_key')
                            ->pluck('push_key')
                            ->toArray();
                    }

                    $body = "지사 댓글이 작성되었습니다.";
                } else if ($row->writer_type == 'm') {
                    if (is_array($rs) && count($rs)) {
                        $arr_push = UserAppInfo::whereIn('user_id', $rs)
                            ->where('album_alarm', 'Y')
                            ->whereNotNull('push_key')
                            ->pluck('push_key')
                            ->toArray();
                    }

                    $body = "교육원 댓글이 작성되었습니다.";
                } else if ($row->writer_type == 's') {
                    if (is_array($rs) && count($rs)) {
                        $student = User::whereIn('id', $rs)->first();

                        if ($student) {
                            $arr_push = UserAppInfo::where('user_id', $row->midx)
                                ->where('album_alarm', 'Y')
                                ->whereNotNull('push_key')
                                ->pluck('push_key')
                                ->toArray();

                            $body = "{$student->name} 학부모 댓글이 작성되었습니다.";
                        }
                    }
                }

                if ($arr_push) {
                    $arr_push = array_unique($arr_push);
                    $arr_push = array_values($arr_push);

                    $pushLog = new PushLog([
                        'type' => $this->type,
                        'type_id' => $this->type_id,
                        'receivers' => json_encode($arr_push)
                    ]);

                    $pushLog->save();

                    if (env('APP_ENV') != 'dev') {
                        $handler = App::make(FcmHandler::class);
                        $handler->setReceivers($arr_push);
                        $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>'album', 'id'=>$row->album_id]);
                        $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>'album', 'id'=>$row->album_id]);
                        $handler->sendMessage();
                    }
                }
            }
        }
        else if ($this->type == 'attendance')
        {
            $row = Attendance::find($this->type_id);

            if ($row) {
                if ($row->sidx) {
                    $user = User::where('id', $row->sidx)->first();

                    if ($user) {
                        $arr_push = UserAppInfo::where('user_id', $row->sidx)
                            ->where('attendance_alarm', 'Y')
                            ->whereNotNull('push_key')
                            ->pluck('push_key')
                            ->toArray();

                        $body = "";
                        $attendance_type = $this->param['type'] ?? "";
                        $attendance_check = $this->param['check'] ?? "";
                        if ($attendance_type == "in" && $attendance_check == "1") {
                            $body = $user->name . " " . date('y년m월d일 H시i분', strtotime($row->in_at)) ."에 등원하였습니다.";
                            if (date('Ymd', strtotime($row->in_at)) != $row->year.$row->month.$row->day) {
                                $body = $user->name . " " . date('y년m월d일 H시i분', strtotime($row->in_at)) ."에 등원 체크하였습니다.";
                            }
                        } else if ($attendance_type == "out" && $attendance_check == "1") {
                            $body = $user->name . " " . date('y년m월d일 H시i분', strtotime($row->out_at)) ."에 하원하였습니다.";
                            if (date('Ymd', strtotime($row->out_at)) != $row->year.$row->month.$row->day) {
                                $body = $user->name . " " . date('y년m월d일 H시i분', strtotime($row->out_at)) ."에 하원 체크하였습니다.";
                            }
                        } else if ($attendance_type == "in" && $attendance_check == "0") {
                            $body = $user->name . " 등원 취소하였습니다.";
                        } else if ($attendance_type == "out" && $attendance_check == "0") {
                            $body = $user->name . " 하원 취소하였습니다.";
                        }

                        if ($arr_push) {
                            $arr_push = array_unique($arr_push);
                            $arr_push = array_values($arr_push);

                            $pushLog = new PushLog([
                                'type' => $this->type,
                                'type_id' => $this->type_id,
                                'receivers' => json_encode($arr_push)
                            ]);

                            $pushLog->save();

                            if (env('APP_ENV') != 'dev') {
                                $handler = App::make(FcmHandler::class);
                                $handler->setReceivers($arr_push);
                                $handler->setMessage(['title'=> $title, 'body'=> $body, 'type'=> $this->type, 'id'=> $row->id, 'userId'=>$row->sidx, 'check'=>$attendance_check]);
                                $handler->setMessageData(['title'=> $title, 'message'=> $body, 'type'=> $this->type, 'id'=> $row->id,'userId'=>$row->sidx,'check'=>$attendance_check]);
                                $handler->sendMessage();
                            }
                        }
                    }
                }
            }
        }
        else if ($this->type == 'educatonInfo')
        {
            $row = EducatonInfo::find($this->type_id);
            if ($row) {
                $arr_push = UserAppInfo::where('adu_info_alarm', 'Y')
                    ->whereNotNull('push_key')
                    ->pluck('push_key')
                    ->toArray();

                $body = "교육정보 '{$row->subject}'이 등록되었습니다.";

                if ($arr_push) {
                    $arr_push = array_unique($arr_push);
                    $arr_push = array_values($arr_push);

                    $pushLog = new PushLog([
                        'type' => $this->type,
                        'type_id' => $this->type_id,
                        'receivers' => json_encode($arr_push)
                    ]);

                    $pushLog->save();

                    if (env('APP_ENV') != 'dev') {
                        $handler = App::make(FcmHandler::class);
                        $handler->setReceivers($arr_push);
                        $handler->setMessage(['title'=> $title, 'body'=> $body, 'type'=> $this->type, 'id'=> $row->id]);
                        $handler->setMessageData(['title'=> $title, 'message'=> $body, 'type'=> $this->type, 'id'=> $row->id]);
                        $handler->sendMessage();
                    }
                }
            }
        }
        else if ($this->type == 'educatonInfoComment')
        {
        }
        else if ($this->type == 'event')
        {
            $row = Event::find($this->type_id);
            if ($row) {
                $arr_push = UserAppInfo::where('event_alarm', 'Y')
                    ->whereNotNull('push_key')
                    ->pluck('push_key')
                    ->toArray();

                $body = "이벤트 '{$row->subject}'이 등록되었습니다.";

                if ($arr_push) {
                    $arr_push = array_unique($arr_push);
                    $arr_push = array_values($arr_push);

                    $pushLog = new PushLog([
                        'type' => $this->type,
                        'type_id' => $this->type_id,
                        'receivers' => json_encode($arr_push)
                    ]);

                    $pushLog->save();

                    if (env('APP_ENV') != 'dev') {
                        $handler = App::make(FcmHandler::class);
                        $handler->setReceivers($arr_push);
                        $handler->setMessage(['title'=> $title, 'body'=> $body, 'type'=> $this->type, 'id'=> $row->id]);
                        $handler->setMessageData(['title'=> $title, 'message'=> $body, 'type'=> $this->type, 'id'=> $row->id]);
                        $handler->sendMessage();
                    }
                }
            }
        }
        else if ($this->type == 'eventComment')
        {
        }
    }

    public function test_push($push_key, $mtype)
    {
        $title = "아소비";
        $body = "알림";
        dump($push_key);
        dump($mtype);
        dump($this->type);
        dump($this->type_id);

        if ($this->type == AdviceNote::ADVICE_TYPE || $this->type == AdviceNote::LETTER_TYPE) {
            $row = AdviceNote::find($this->type_id);
            if ($row) {
                $student = User::where('id', $row->sidx)->first();
                if ($student) {
                    if ($row->type == AdviceNote::ADVICE_TYPE) {
                        $body = "{$student->name}의 알림장이 작성되었습니다.";
                    } else if ($row->type == AdviceNote::LETTER_TYPE) {
                        $body = "{$student->name}의 {$row->month}월 가정통신문이 작성되었습니다.";
                    }

                    if ($push_key) {
                        $handler = App::make(FcmHandler::class);
                        $handler->setReceivers([$push_key]);
                        $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                        $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                        $handler->sendMessage();
                    }
                }
            }
        } else if ($this->type == "adviceComment") {
            $row = AdviceComment::find($this->type_id);
//            \App::make('helper')->log('adviceComment', ['bbb' => $this->type_id], 'adviceComment');
            if($row){
                if ($row->writer_type == 'a'){
                    $body = "본사 댓글이 작성되었습니다.";
                } else if($row->writer_type == 'h'){
                    $body = "지사 댓글이 작성되었습니다.";
                } else if($row->writer_type == 'm'){
                    $body = "교육원 댓글이 작성되었습니다.";
                } else if($row->writer_type == 's'){
                    $student = User::where('id', $row->sidx)->first();
                    if($student){
                        $body = "{$student->name} 학부모 댓글이 작성되었습니다.";
                    }
                }
                $parent_row = AdviceNote::where('id', $row->advice_note_id)->first();
                if ($push_key) {
                    $handler = App::make(FcmHandler::class);
                    $handler->setReceivers([$push_key]);
                    $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$parent_row->type, 'id'=>$parent_row->id,'userId'=>$parent_row->sidx]);
                    $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$parent_row->type, 'id'=>$parent_row->id,'userId'=>$parent_row->sidx]);
                    $handler->sendMessage();
                }
            }
        } else if ($this->type == "notice") {
            $row = Notice::find($this->type_id);
            if ($row) {
                if ($row->writer_type == 'm') {
                    $rs = User::where('center_id', $row->midx)->where('user_type', 's')->where('status', 'Y')->pluck('id')->toArray();
                    $body = "[공지] 교육원 공지사항이 작성되었습니다.";
                } else if ($row->writer_type == 'h') {
                    $rs = User::where('branch_id', $row->midx)->where('user_type', 'm')->where('status', 'Y')->pluck('id')->toArray();
                    $body = "[공지] 지사 공지사항이 작성되었습니다.";
                } else if ($row->writer_type == 'a') {
                    $rs = User::where('user_type', 's')->where('status', 'Y')->pluck('id')->toArray();
                    $body = "[공지] 공지사항이 작성되었습니다.";
                }
                if ($push_key) {
                    $handler = App::make(FcmHandler::class);
                    $handler->setReceivers([$push_key]);
                    $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                    $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                    $handler->sendMessage();
                }
            }
        } else if ($this->type == "appNotice") {
            $row = AppNotice::find($this->type_id);
            if ($row) {
                if ($row->read_branch == 'Y') {
                    $branch_rs = User::where('user_type', 'h')->where('status', 'Y')->pluck('id')->toArray();
                    $center_rs = User::where('user_type', 'm')->where('status', 'Y')->pluck('id')->toArray();
                    $rs = array_merge($branch_rs, $center_rs);
                } else if ($row->read_center == 'Y') {
                    $rs = User::where('branch_id', $row->hidx)->where('user_type', 'm')->where('status', 'Y')->pluck('id')->toArray();
                }
                if ($push_key) {
                    $body = "[공지] 아소비교육 공지사항이 작성되었습니다.";
                    $handler = App::make(FcmHandler::class);
                    $handler->setReceivers([$push_key]);
                    $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                    $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                    $handler->sendMessage();
                }
            }
        } else if ($this->type == "album") {
            $row = Album::find($this->type_id);
            if ($row) {
                if ($row->sidx) {
                    $rs = json_decode($row->sidx);
                    if (is_array($rs) && sizeof($rs)) {
                        if ($push_key) {
                            $body = "교육원 앨범이 작성되었습니다.";
                            $handler = App::make(FcmHandler::class);
                            $handler->setReceivers([$push_key]);
                            $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                            $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>$this->type, 'id'=>$row->id]);
                            $handler->sendMessage();
                        }
                    }
                }
            }
        } else if ($this->type == "albumComment") {
            $row = AlbumComment::find($this->type_id);
            if($row){
                if ($row->writer_type == 'a') {
                    $body = "본사 댓글이 작성되었습니다.";
                } else if($row->writer_type == 'h'){
                    $body = "지사 댓글이 작성되었습니다.";
                } else if($row->writer_type == 'm'){
                    $body = "교육원 댓글이 작성되었습니다.";
                } else if($row->writer_type == 's'){
                    $student = User::where('id', $row->sidx)->first();
                    if($student){
                        $body = "{$student->name} 학부모 댓글이 작성되었습니다.";
                    }
                }
                if ($push_key) {
                    $handler = App::make(FcmHandler::class);
                    $handler->setReceivers([$push_key]);
                    $handler->setMessage(['title' => $title, 'body' => $body, 'type'=>'album', 'id'=>$row->album_id]);
                    $handler->setMessageData(['title' => $title, 'message' => $body, 'type'=>'album', 'id'=>$row->album_id]);
                    $handler->sendMessage();
                }
            }
        }
    }
}
