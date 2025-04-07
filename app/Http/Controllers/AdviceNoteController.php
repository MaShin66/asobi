<?php

namespace App\Http\Controllers;

use App\AdviceFile;
use App\AdviceNote;
use App\AdviceNoteAdmin;
use App\AdviceComment;
use App\AdviceNoteHistory;
use App\AdviceNoteShareHistory;
use App\AppendFile;
use App\File;
use App\Jobs\BatchPush;
use App\Jobs\BatchPost;
use App\Models\RaonMember;
use App\RequestLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use App\Rules\UploadFile;
use Validator;
use \Illuminate\Http\UploadedFile;

//use Vimeo\Laravel\Facades\Vimeo;
use Vimeo\Laravel\VimeoManager;

class AdviceNoteController extends Controller
{
    /**
        select * from `advice_notes` where `type` in ('letter', 'advice')
        #and `sidx` = 134423
        #and `year` = ? and `month` = ?
        and `advice_notes`.`deleted_at` is null
        and sidx in (select id from users where center_id in (select id from users where user_type = 'm') and user_type = 's' and status = 'Y' order by name asc)
        order by `created_at` desc
        limit 1000;
     *
     * 기존 루틴은 N+1 쿼리를 날려서 아래 루틴으로 교체
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function student(Request $request)
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
        $year = $request->input('year') ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d', $request->input('month')) : $now->format('m');
        $day = $request->input('day') ? sprintf('%02d', $request->input('day')) : null;

        $search_user_id = $request->input('search_user_id') ?? "";

        $rs = RaonMember::where('midx', $user->idx)
            ->where('mtype', 's')
            ->where('s_status', 'Y')
            ->when($search_user_id != "", function ($q) use ($search_user_id) {
                $q->where('idx', $search_user_id);
            })
            ->orderBy('name', 'asc')
            ->get();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            $users = $rs->pluck('idx')->toArray();
//            $result = Arr::add($result, 'users', $users);
            $adviceObj = AdviceNote::where('type', AdviceNote::ADVICE_TYPE)
                ->whereIn('sidx', $users)
                ->where('year', $year)
                ->where('month', $month);
            $letterObj = AdviceNote::where('type', AdviceNote::LETTER_TYPE)
                ->whereIn('sidx', $users)
                ->where('year', $year)
                ->where('month', $month)
                ->orderBy('created_at', 'DESC');
            if ($day) {
                $adviceRs = $adviceObj->where('day', $day)->get();
                $letterRs = $letterObj->where('day', $day)->get();
            } else {
                $adviceRs = $adviceObj->get();
                $letterRs = $letterObj->get();
            }

            $adviceArr = [];
            if ($adviceRs) {
                if ($day) {
                    $adviceArr = $adviceRs->keyBy('sidx')->toArray();
                } else {
                    foreach ($adviceRs as $l) {
                        $adviceArr[$l->sidx][] = $l;
                    }
                }
            }
            $letterArr = [];
            if ($letterRs) {
                if ($day) {
                    $letterArr = $letterRs->keyBy('sidx')->toArray();
                } else {
                    foreach ($letterRs as $l) {
                        $letterArr[$l->sidx][] = $l;
                    }
                }
            }

            $result = Arr::add($result, 'letter_count', count($letterArr));

            foreach ($rs as $index => $row) {
                $userMemberDetail = RaonMember::where('idx', $row->idx)->first();
                $profile_image = $userMemberDetail->user_picture ?? '';

                $result = Arr::add($result, "list.{$index}.id", $row->idx);
                $result = Arr::add($result, "list.{$index}.name", $row->name);
                $result = Arr::add($result, "list.{$index}.profile_image", $profile_image ? \App::make('helper')->getImage($profile_image) : null);

                $advice = $adviceArr[$row->idx] ?? [];
                $letter = $letterArr[$row->idx] ?? [];
                if ($day) {
                    $result = Arr::add($result, "list.{$index}.advice", $advice['id'] ?? null);
                    $result = Arr::add($result, "list.{$index}.letter", $letter['id'] ?? null);
                    $result = Arr::add($result, "list.{$index}.letter_batch", $letter['batch'] ?? null);
                } else {
                    $result = Arr::add($result, "list.{$index}.advice", count($advice));
                    $result = Arr::add($result, "list.{$index}.letter", count($letter));
                    $result = Arr::add($result, "list.{$index}.letter_id", count($letter) > 0 ? $letter[0]->id : null);
                    $result = Arr::add($result, "list.{$index}.letter_batch", count($letter) > 0 ? $letter[0]->batch : null);
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

        if (!in_array($user->mtype, ['s'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $now = Carbon::now();
        $year = $request->input('year') ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d', $request->input('month')) : $now->format('m');
        $search_text = $request->input('search_text') ?? '';
        $search_text = trim($search_text);

        $rs = AdviceNote::with('files')
            ->where($user->mtype . 'idx', $user->idx)
            ->where('status', 'Y')
            ->where('year', $year)
            ->where('month', $month)
            ->when($search_text, function ($q) use ($search_text){
                $q->where(function ($query) use ($search_text) {
                    $query->where('advice_notes.content','like','%'.$search_text.'%')
                        ->orWhere('advice_notes.title', 'like', '%'.$search_text.'%');
                });
            })
            ->orderByDesc('type')
            ->orderByDesc('created_at')
            ->get();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            foreach ($rs as $index => $row) {
                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.title", $row->title);
                $result = Arr::add($result, "list.{$index}.content", $row->content);
                $this_date = Carbon::create($row->year, $row->month, $row->day);
                $result = Arr::add($result, "list.{$index}.date", $this_date->format('Y.m.d')." ".\App::make('helper')->dayOfKo($this_date, 2));
                $result = Arr::add($result, "list.{$index}.reg_date", $row->created_at->format(AdviceNote::REG_DATE_FORMAT));
                $result = Arr::add($result, "list.{$index}.type", $row->type);
                $result = Arr::add($result, "list.{$index}.type_name", ($row->type == 'advice')?"알림장":"가정통신문");
                $result = Arr::add($result, "list.{$index}.student", $row->sidx);

                if ($row->files->count()) {
                    foreach ($row->files as $file_index => $file) {
                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_id", $file->id);
                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_name", $file->file_name);

//                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_path", $file->vimeo_id ? AppendFile::getVimeoThumbnailUrl($file->vimeo_id) : Storage::disk('s3')->url($file->file_path));
//                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_path", $file->vimeo_id ? AppendFile::getVimeoThumbnailUrl($file->vimeo_id) : \App::make('helper')->getImage($file->file_path));
                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_path", $file->vimeo_id ? null : \App::make('helper')->getImage($file->file_path));
                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.vimeo_id", $file->vimeo_id ? $file->vimeo_id : null);
                    }
                }
            }
        }

        return response()->json($result);
    }

    public function show(Request $request, $adviceNote_id)
    {
        $result = array();
        $modify = $request->input('modify') ?? '';
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->mtype, ['m','s'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        if ($user->mtype == 's') {
            $children_rs = RaonMember::where('mobilephone', $user->mobilephone)->where(
                'pw',
                $user->pw
            )->where('mtype', 's')->where('s_status', 'Y')->get();
            if (sizeof($children_rs) > 1) {
                $check_row = AdviceNote::find($adviceNote_id);
                if ($check_row) {
                    foreach ($children_rs as $children_row) {
                        if ($check_row->sidx == $children_row->id) {
                            $user = $children_row;
                        }
                    }
                }
            }
        }

        $row = AdviceNote::with('files')
            ->where($user->mtype . 'idx', $user->idx)
            ->where('status', 'Y')
            ->whereId($adviceNote_id)
            ->first();

        if (empty($row)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, "id", $row->id);
        $result = Arr::add($result, "type", $row->type);
        $result = Arr::add($result, "type_name", ($row->type == 'advice')?"알림장":"가정통신문");
        $result = Arr::add($result, "title", $row->title);
        $result = Arr::add($result, "content", $row->content);
//        dd($row);
        $this_date = Carbon::create($row->year, $row->month, $row->day);


        $result = Arr::add($result, "date", $this_date->format('Y.m.d')." / ".$row->created_at->format('Y.m.d H:i'));
        $result = Arr::add($result, "date2", $this_date->format('Y-m-d'));
        $result = Arr::add($result, "ym", $this_date->format('Y-m'));
//        $result = Arr::add($result, "reg_date", $row->created_at->format(AdviceNote::REG_DATE_FORMAT));
        $result = Arr::add($result, "student", $row->sidx);
        $student = RaonMember::whereIdx($row->sidx)->first();
        $result = Arr::add($result, "student_name", $student->name);

        if ($row->type == AdviceNote::LETTER_TYPE) {
            $adviceNoteAdmin = AdviceNoteAdmin::whereThisMonth($row->this_month)->first();
            if ($adviceNoteAdmin) {
                $result = Arr::add($result, "prefix_content", $adviceNoteAdmin->prefix_content);
                $result = Arr::add($result, "this_month_education_info", $adviceNoteAdmin->this_month_education_info);
            } else {
                $result = Arr::add($result, "prefix_content", null);
                $result = Arr::add($result, "this_month_education_info", null);
            }

            $this_course = DB::connection('mysql')->table('order AS o')
                ->join('order_member_detail AS omd', 'omd.order_idx', '=', 'o.idx')
                ->join('shopProduct AS sp', 'sp.idx', '=', 'omd.product_idx')
                ->select('sp.name', 'sp.content')
                ->where('o.course', $row->this_month)
                ->where('o.status', '33')
                ->where('omd.sidx', $row->sidx)
                ->where('omd.order_type', 'B')
                ->where('omd.status', '33')
                //->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail_cancellations` WHERE `order_member_detail_id` = omd.idx)"), '=', 0)
                //->where(DB::raw("(SELECT COUNT(*) FROM `order_cancel_detail` WHERE `order_member_idx` = omd.order_member_idx )"), '=', 0)
                //->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail` WHERE `order_member_idx` = omd.idx AND `status` = '99')"), '=', 0)
                // 2024-03-29 박성복 수정 - 기존 API 쿼리문으로 수정함 Start
                ->where('omd.cancel', '0')
                ->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail` WHERE `cancel` = omd.idx)"), '<', 1)
                ->where(DB::raw("(SELECT COUNT(*) FROM `order_cancel_detail` WHERE `order_member_detail_idx` = omd.idx AND `status` = '99')"), '=', 0)
                // 2024-03-29 박성복 수정 - 기존 API 쿼리문으로 수정함 End
                ->orderBy('omd.idx', 'ASC')
                ->get();

            $next_course = DB::connection('mysql')->table('order AS o')
                ->join('order_member_detail AS omd', 'omd.order_idx', '=', 'o.idx')
                ->join('shopProduct AS sp', 'sp.idx', '=', 'omd.product_idx')
                ->select('sp.name', 'sp.content')
                ->where('o.course', $row->next_month)
                ->where('o.status', '33')
                ->where('omd.sidx', $row->sidx)
                ->where('omd.order_type', 'B')
                ->where('omd.status', '33')
//              //->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail_cancellations` WHERE `order_member_detail_id` = omd.idx)"), '=', 0)
                //->where(DB::raw("(SELECT COUNT(*) FROM `order_cancel_detail` WHERE `order_member_idx` = omd.order_member_idx)"), '=', 0)
                //->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail` WHERE `order_member_idx` = omd.idx AND `status` = '99')"), '=', 0)
                // 2024-03-29 박성복 수정 - 기존 API 쿼리문으로 수정함 Start
                ->where('omd.cancel', '0')
                ->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail` WHERE `cancel` = omd.idx)"), '<', 1)
                ->where(DB::raw("(SELECT COUNT(*) FROM `order_cancel_detail` WHERE `order_member_detail_idx` = omd.idx AND `status` = '99')"), '=', 0)
                // 2024-03-29 박성복 수정 - 기존 API 쿼리문으로 수정함 End
                ->orderBy('omd.idx', 'ASC')
                ->get();

            if ($this_course->count()) {
                foreach ($this_course as $course_index => $course) {
                    $result = Arr::add($result, "this_schedule.{$course_index}.name", $course->name);
                    $result = Arr::add($result, "this_schedule.{$course_index}.content", strip_tags($course->content));
                } // foreach End
            } else {
                $result = Arr::add($result, "this_schedule", null);
            }

            if ($next_course->count()) {
                foreach ($next_course as $course_index => $course) {
                    $result = Arr::add($result, "next_schedule.{$course_index}.name", $course->name);
                    $result = Arr::add($result, "next_schedule.{$course_index}.content", strip_tags($course->content));
                } // foreach End
            } else {
                $result = Arr::add($result, "next_schedule", null);
            }

            $this_date = Carbon::create($row->this_month);
            $this_month = $this_date->format('m');
            $next_month = $this_date->addMonth(1)->format('m');

            $result = Arr::add($result, "class_content", $row->class_content);
            $result = Arr::add($result, "this_month", $this_month);
            $result = Arr::add($result, "next_month", $next_month);
        }

        if ($row->files->count()) {
            foreach ($row->files as $file_index => $file) {
                $result = Arr::add($result, "file.{$file_index}.file_id", $file->id);
                $result = Arr::add($result, "file.{$file_index}.file_name", $file->file_name);
//                $result = Arr::add(
//                    $result,
//                    "file.{$file_index}.file_path",
//                    $file->vimeo_id ? AppendFile::getVimeoThumbnailUrl(
//                        $file->vimeo_id
//                    ) : \App::make('helper')->getImage($file->file_path)
//                );
//                $result = Arr::add(
//                    $result,
//                    "file.{$file_index}.video_path",
//                    $file->vimeo_id ? AppendFile::getVimeoUrl($file->vimeo_id) : null
//                );
                $vimeo_file = null;
                if ($modify == "1" && $file->vimeo_id) $vimeo_file = AppendFile::getVimeoThumbnailUrl($file->vimeo_id);
                $result = Arr::add(
                    $result,
                    "file.{$file_index}.file_path",
                    $file->vimeo_id ? $vimeo_file : \App::make('helper')->getImage($file->file_path)
                );
                $result = Arr::add(
                    $result,
                    "file.{$file_index}.vimeo_id",
                    $file->vimeo_id ? $file->vimeo_id : null
                );
            }
        }

        if ($user->mtype == 's') {
            if ($row->histories->where('sidx', $user->idx)->count() === 0) {
                $row->histories()->create(
                    [
                        'hidx' => $user->hidx,
                        'midx' => $user->midx,
                        'sidx' => $user->idx
                    ]
                );
            }
        } else {
            if ($user->mtype == 'm') {
                if ($row->histories->where('midx', $user->idx)->count() === 0) {
                    $row->histories()->create(
                        [
                            'hidx' => $user->hidx,
                            'midx' => $user->midx
                        ]
                    );
                }
            } else {
                if ($user->mtype == 'h') {
                    if ($row->histories->where('hidx', $user->idx)->count() === 0) {
                        $row->histories()->create(
                            [
                                'hidx' => $user->hidx
                            ]
                        );
                    }
                }
            }
        }

        if ($user->mtype == 'm') {
            $adviceNoteHistory = AdviceNoteHistory::where('hidx', $user->hidx)
                ->where('midx', $user->idx)
                ->where('sidx', $student->idx)
                ->where('advice_note_id', $row->id)
                ->first();

            $readed = $adviceNoteHistory ? 'Y' : 'N';

            $result = Arr::add($result, "readed", $readed);
        }

        return response()->json($result);
    }

    public function create(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        $student = $request->input('student');

        if (!($user->mtype == 'm' && $student)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $now = Carbon::now();
        $year = $request->input('year') ? $request->input('year') : $now->format('Y');
        $month = $request->input('month') ? $request->input('month') : $now->format('m');
        $day = $request->input('day') ? $request->input('day') : $now->format('d');
        $this_date = Carbon::create($year, $month);
        $this_month = $this_date->format('Y-m');
        $next_month = $this_date->addMonth(1)->format('Y-m');

        $result = Arr::add($result, 'result', 'success');

        $adviceNoteAdmin = AdviceNoteAdmin::whereThisMonth($this_month)->first();
        if ($adviceNoteAdmin) {
            $result = Arr::add($result, "prefix_content", $adviceNoteAdmin->prefix_content);
            $result = Arr::add(
                $result,
                "this_month_education_info",
                $adviceNoteAdmin->this_month_education_info
            );
        } else {
            $result = Arr::add($result, "prefix_content", null);
            $result = Arr::add($result, "this_month_education_info", null);
        }

        $this_course = DB::connection('mysql')->table('order AS o')
            ->join('order_member_detail AS omd', 'omd.order_idx', '=', 'o.idx')
            ->join('shopProduct AS sp', 'sp.idx', '=', 'omd.product_idx')
            ->select('sp.name', 'sp.content')
            ->where('o.course', $this_month)
            ->where('o.status', '33')
            ->where('omd.sidx', $student)
            ->where('omd.order_type', 'B')
            ->where('omd.status', '33')
//            ->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail_cancellations` WHERE `order_member_detail_id` = omd.idx)"), '=', 0)
            ->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail` WHERE `order_member_id` = omd.idx AND `status` = '99')"), '=', 0)
            ->orderBy('omd.idx', 'ASC')
            ->get();

        $next_course = DB::connection('mysql')->table('order AS o')
            ->join('order_member_detail AS omd', 'omd.order_idx', '=', 'o.idx')
            ->join('shopProduct AS sp', 'sp.idx', '=', 'omd.product_idx')
            ->select('sp.name', 'sp.content')
            ->where('o.course', $next_month)
            ->where('o.status', '33')
            ->where('omd.sidx', $student)
            ->where('omd.order_type', 'B')
            ->where('omd.status', '33')
//            ->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail_cancellations` WHERE `order_member_detail_id` = omd.idx)"), '=', 0)
            ->where(DB::raw("(SELECT COUNT(*) FROM `order_member_detail` WHERE `order_member_id` = omd.idx AND `status` = '99')"), '=', 0)
            ->orderBy('omd.idx', 'ASC')
            ->get();

        if ($this_course->count()) {
            foreach ($this_course as $course_index => $course) {
                $result = Arr::add($result, "this_schedule.{$course_index}.name", $course->name);
                $result = Arr::add($result, "this_schedule.{$course_index}.content", strip_tags($course->content));
            }
        } else {
            $result = Arr::add($result, "this_schedule", null);
        }

        if ($next_course->count()) {
            foreach ($next_course as $course_index => $course) {
                $result = Arr::add($result, "next_schedule.{$course_index}.name", $course->name);
                $result = Arr::add($result, "next_schedule.{$course_index}.content", strip_tags($course->content));
            }
        } else {
            $result = Arr::add($result, "next_schedule", null);
        }

        $advice_note_letter = AdviceNote::where('midx', $user->idx)
            ->where('sidx', $student)
            ->where('type', AdviceNote::LETTER_TYPE)
            ->where('year', $year)
            ->where('month', $month)
            ->count();

        // @2021-10-01 마지막주만 작성가능
        $now_year_month = date('Y-m');
        $last_day = date('t', strtotime($now_year_month . '-01'));
        $write_possible_date = strtotime(date('Y-m-d 00:00:00', strtotime($now_year_month . '-' . $last_day . ' -5 day')));
        $now_date = strtotime(date('Y-m-d H:i:s', time()));

        $result = Arr::add($result, "write_possible_date", date('Y-m-d H:i:s', $write_possible_date)."~");

//        $result = Arr::add($result, "now_year_month", $now_year_month);
//        $result = Arr::add($result, "last_day", $last_day);
//        $result = Arr::add($result, "now_date", $now_date);

        if ($write_possible_date < $now_date) {
            $result = Arr::add($result, "write_possible", true);
            $result = Arr::add($result, "this_month_letter_count", $advice_note_letter);
        } else {
            $result = Arr::add($result, "write_possible", false);
            $result = Arr::add($result, "this_month_letter_count", 1);
        }

//        $result = Arr::add($result, "write_possible", true);
//        $result = Arr::add($result, "this_month_letter_count", $advice_note_letter);

        return response()->json($result);
    }

    public function checkLetter(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if ($user->mtype != 'm') {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $now = Carbon::now();
        $year = $request->input('year') ? sprintf('%04d',$request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d',$request->input('month')) : $now->format('m');

//         @2021-10-01 마지막주만 작성가능
//        $result = Arr::add($result, "write_possible", true);

        $now_year_month = date('Y-m');
        $last_day = date('t', strtotime($now_year_month . '-01'));
        $write_possible_date = strtotime(date('Y-m-d 00:00:00', strtotime($now_year_month . '-' . $last_day . ' -5 day')));
        $now_date = strtotime(date('Y-m-d H:i:s', time()));

        $result = Arr::add($result, "write_possible_date", date('Y-m-d H:i:s', $write_possible_date)."~");

        if ($write_possible_date < $now_date && $now_year_month == $year."-".$month || session('auth')['user_type'] == 'a') {
            $result = Arr::add($result, "write_possible", true);
        } else {
            $result = Arr::add($result, "write_possible", false);
        }

//        if ($_SERVER['REMOTE_ADDR'] === '221.148.221.127') {
//            dd($user->mtype);
//        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        $validator = Validator::make($request->all(), [
            'upload_files' => [new UploadFile],
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => "업로드 하려는 파일은 동영상, 이미지만 가능하고 이미지는 10Mb이하, 동영상은 100Mb 이하로만 가능합니다."
            ]);
        }

        $arr_request_file = array();
        if ($request->allFiles()) {
            $file_index = 0;
            foreach ($request->allFiles() as $files) {
                foreach ($files as $file) {
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.name", $file->getClientOriginalName());
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.size", $file->getSize());
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.mime", $file->getMimeType());
                }
            }
        }

        RequestLog::create(
            [
                'user' => $user_id,
                'request_url' => URL::current(),
                'request_data' => json_encode($request->all()),
                'request_file' => json_encode($arr_request_file)
            ]
        );

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

        $type = $request->input('type') ? $request->input('type') : AdviceNote::ADVICE_TYPE;
        if (!in_array($type, [AdviceNote::LETTER_TYPE, AdviceNote::ADVICE_TYPE])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '올바른 타입이 아닙니다.');
            return response()->json($result);
        }

        $is_write = true;
        $title = null;
        $this_month = null;
        $next_month = null;
        $write_not_possible = false;

        //$title = $request->input('title');
        $content = $request->input('content');
        $class_content = $request->input('class_content');
        $student = $request->input('student');

        if (!is_array($student) || count($student) == 0) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '학생을 선택해주세요.');
            return response()->json($result);
        }

        $now = Carbon::now();
        $year = $request->input('year') ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d', $request->input('month')) : $now->format('m');
        $day = $request->input('day') ? sprintf('%02d', $request->input('day')) : $now->format('d');
        $debug_write = $request->input('debug_write') ? true : false;
        if (env('APP_ENV') != 'development') $debug_write = false;

        $id = $request->input('id') ?? null;

        $adviceNote = AdviceNote::whereId($id)->first();

        if ($adviceNote) {
            $adviceNote->content = $content;
            $adviceNote->class_content = $class_content;

            $adviceNote->save();

            $result = Arr::add($result, 'result', 'success');
            $result = Arr::add($result, 'error', '등록 되었습니다.');
            $result = Arr::add($result, 'ids', $id);

            return response()->json($result);
        }

        if ($type == AdviceNote::LETTER_TYPE) {
            $this_date = Carbon::create($year, $month);
            $this_month = $this_date->format('Y-m');
            $next_month = $this_date->addMonth(1)->format('Y-m');

            $check_letter = AdviceNote::select(['id', 'sidx'])
                ->where('type', $type)
                ->whereIn('sidx', $student)
                ->where('this_month', $this_month)
                ->where('status', 'Y')
                ->get();

            if ($check_letter->count() < 1) {
                // @2021-10-01 마지막주만 작성가능
                $now_year_month = date('Y-m');
                if ($debug_write) $now_year_month = $year."-".$month;

                $last_day = date('t', strtotime($now_year_month . '-01'));
                $write_possible_date = strtotime(date('Y-m-d 00:00:00', strtotime($now_year_month . '-' . $last_day . ' -5 day')));
                $now_date = strtotime(date('Y-m-d H:i:s', time()));

                if ($debug_write) $now_date = strtotime(date('Y-m-d 00:00:00', strtotime($now_year_month . '-' . $last_day . ' -4 day')));

                if ($write_possible_date < $now_date) {
                    $is_write = true;
                } else {
                    $is_write = false;
                    $write_not_possible = true;
                }

                unset($last_day);
                unset($write_possible_date);
                unset($now_date);
            } else {
                $is_write = false;
            }
        }

//        $is_write = true;

        if (!$is_write) {
            if ($write_not_possible === true) {
                $result = Arr::add($result, 'result', 'fail');
                $result = Arr::add($result, 'error', '작성기간이 아닙니다.');
                $result = Arr::add($result, 'errorResult', 'Y');
            } else {
                $result = Arr::add($result, 'result', 'success');
                $result = Arr::add($result, 'error', '이미 작성 되었습니다.');
                if (isset($check_letter) && $check_letter->count() > 0) {
                    $names = [];
                    if (isset($check_letter) && $check_letter->count() > 0) {
                        $name_arr = RaonMember::select('name')
                            ->whereIn('id', $check_letter->pluck('sidx')->toArray())
                            ->get()
                            ->toArray();
                        if (count($name_arr) > 0) {
                            foreach ($name_arr as $k => $l) {
                                $names[] = $l['name'];
                            }
                        }
                    }
                    $result = Arr::add($result, 'err_user_names', $names);
                    $result = Arr::add($result, 'err_user_ids', $check_letter->pluck('sidx')->toArray());
                    $result = Arr::add($result, 'err_content_ids', $check_letter->pluck('id')->toArray());
                }
                $result = Arr::add($result, 'errorResult', 'Y');
            }
            return response()->json($result);
        }

        if ($type == AdviceNote::LETTER_TYPE) {
            $title = $month . "월 가정통신문";
        }

        $adviceNoteIds = [];
//        BatchPost::dispatch($student, $type, $user, $title, $content, $class_content, $year, $month, $day, $this_month, $next_month, $request);
        foreach ($student as $l) {
            if ($type == AdviceNote::ADVICE_TYPE) {
                $student_row = RaonMember::whereIdx($l)->first();
                if ($student_row) {
                    $title = $student_row->name . "의 선생님이 알립니다.";

//                    dd($ymd);
//
//                    $year = null;
//                    $month = null;
//                    $day = 1;
//                    $this_month = null;
//                    $next_month = null;
                }
            }
            $payload = [
                'type' => $type,
                'hidx' => $user->hidx,
                'midx' => $user->idx,
                'sidx' => $l,
                'title' => $title,
                'content' => $content,
                'class_content' => $class_content,
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'this_month' => $this_month,
                'next_month' => $next_month,
                'status' => 'Y',
                'batch' => 'N'
            ];

//            dd($payload);

            $adviceNote = new AdviceNote($payload);
            $adviceNote->save();

            //임시파일 업로드
            $tmp_upload = $request->input('upload_files');
            $upload_files = $request->file('upload_files');
            if ($tmp_upload) {
                if ($upload_files && is_array($upload_files)) {
                    $upload_files = array_merge($tmp_upload, $upload_files);
                } else {
                    $upload_files = $tmp_upload;
                }
            }
            if ($upload_files) {
                $vimeo = new VimeoController();
                foreach ($upload_files as $file) {
                    $file_name = $file->getClientOriginalName();
                    $vimeo_id = null;

                    if (Str::startsWith($file->getMimeType(), 'video')) {
                        $vimeo_id = $vimeo->upload_simple($file);
                    }

                    if ($vimeo_id) {
                        $file_path = AppendFile::getVimeoThumbnailUrl($vimeo_id);
                    } else {
                        $file = \App::make('helper')->rotateImage($file);
                        $file_path = \App::make('helper')->putResizeS3(AdviceFile::FILE_DIR, $file);
                    }

                    $adviceNote->files()->create(
                        [
                            'file_name' => $file_name,
                            'file_path' => $file_path,
                            'file_size' => $file->getSize(),
                            'file_mimetype' => $file->getMimeType(),
                            'vimeo_id' => $vimeo_id
                        ]
                    );
                }
            }
            $adviceNoteIds[] = $adviceNote->id;
//            $push = new PushMessageController($adviceNote->type, $adviceNote->id);
//            $push->push();

            BatchPush::dispatch(['type' => $adviceNote->type, 'type_id' => $adviceNote->id, 'param' => []]);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '등록 되었습니다.');
        $result = Arr::add($result, 'ids', $adviceNoteIds);

        return response()->json($result);
    }

    public function update(Request $request, $adviceNote_id)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        $validator = Validator::make($request->all(), [
            'upload_files' => [new UploadFile],
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => "업로드 하려는 파일은 동영상, 이미지만 가능하고 이미지는 10Mb이하, 동영상은 100Mb 이하로만 가능합니다."
            ]);
        }

        $arr_request_file = array();
        if ($request->allFiles()) {
            $file_index = 0;
            foreach ($request->allFiles() as $files) {
                foreach ($files as $file) {
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.name", $file->getClientOriginalName());
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.size", $file->getSize());
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.mime", $file->getMimeType());
                }
            }
        }

        RequestLog::create(
            [
                'user' => $user_id,
                'request_url' => URL::current(),
                'request_data' => json_encode($request->all()),
                'request_file' => json_encode($arr_request_file)
            ]
        );

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

        $type = $request->input('type') ? $request->input('type') : AdviceNote::ADVICE_TYPE;
        if (!in_array($type, [AdviceNote::LETTER_TYPE, AdviceNote::ADVICE_TYPE])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '올바른 타입이 아닙니다.');
            return response()->json($result);
        }

        $content = $request->input('content');
        $class_content = $request->input('class_content');
        $student = $request->input('student');
        $adviceNote = AdviceNote::whereId($adviceNote_id)->where('midx', $user->idx)->where('sidx', $student)->first();
        if (empty($adviceNote)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            return response()->json($result);
        }

        $payload = [
            'type' => $type,
            'content' => $content,
            'class_content' => $class_content
        ];
        $adviceNote->fill($payload);
        $adviceNote->save();

        $upload_files = $request->file('upload_files');
        if ($upload_files) {
            $vimeo = new VimeoController();
            foreach ($upload_files as $file) {
                $file_name = $file->getClientOriginalName();
                $vimeo_id = null;

                if (Str::startsWith($file->getMimeType(), 'video')) {
                    $vimeo_id = $vimeo->upload_simple($file);
                }

                if ($vimeo_id) {
                    $file_path = AppendFile::getVimeoThumbnailUrl($vimeo_id);
                } else {
                    $file = \App::make('helper')->rotateImage($file);
                    $file_path = \App::make('helper')->putResizeS3(AdviceFile::FILE_DIR, $file);
                }

                $adviceNote->files()->create(
                    [
                        'file_name' => $file_name,
                        'file_path' => $file_path,
                        'file_size' => $file->getSize(),
                        'file_mimetype' => $file->getMimeType(),
                        'vimeo_id' => $vimeo_id
                    ]
                );
            }
        }
        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '수정 되었습니다.');

        return response()->json($result);
    }

    public function storeAll(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');

        $user = RaonMember::whereIdx($user_id)->first();

        $arr_request_file = array();
        if ($request->allFiles()) {
            $file_index = 0;
            foreach ($request->allFiles() as $files) {
                foreach ($files as $file) {
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.name", $file->getClientOriginalName());
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.size", $file->getSize());
                    $arr_request_file = Arr::add($arr_request_file, "upload.{$file_index}.mime", $file->getMimeType());
                }
            }
        }

        RequestLog::create(
            [
                'user' => $user_id,
                'request_url' => URL::current(),
                'request_data' => json_encode($request->all()),
                'request_file' => json_encode($arr_request_file)
            ]
        );

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

        $type = AdviceNote::LETTER_TYPE;
        $content = $request->input('content') ? $request->input('content') : '';
        $class_content = $request->input('class_content') ? $request->input('class_content') : '';

        $now = Carbon::now();
        $year = $request->input('year') ? $request->input('year') : $now->format('Y');
        $month = $request->input('month') ? $request->input('month') : $now->format('m');
        $day = $request->input('day') ? $request->input('day') : $now->format('d');

        $title = $month . "월 가정통신문";

        $this_date = Carbon::create($year, $month);
        $this_month = $this_date->format('Y-m');
        $next_month = $this_date->addMonth(1)->format('Y-m');
        $debug_write = $request->input('debug_write') ? true : false;
        if (env('APP_ENV') != 'development') $debug_write = false;

        $studentCount = RaonMember::where('midx', $user->idx)
            ->where('mtype', 's')
            ->where('s_status', 'Y')
            ->count();

        $advice_note_count = AdviceNote::where('type', $type)
            ->where('midx', $user->idx)
            ->where('this_month', $this_month)
            ->where('status', 'Y')
            ->count();

        $is_write = false;
        $write_not_possible = false;

        if ($advice_note_count < $studentCount) {
            // @2021-10-01 마지막주만 작성가능
            $now_year_month = date('Y-m');
            if ($debug_write) $now_year_month = $year."-".$month;

            $last_day = date('t', strtotime($now_year_month . '-01'));
            $write_possible_date = strtotime(date('Y-m-d 00:00:00', strtotime($now_year_month . '-' . $last_day . ' -5 day')));
            $now_date = strtotime(date('Y-m-d H:i:s', time()));

            if ($debug_write) $now_date = strtotime(date('Y-m-d 00:00:00', strtotime($now_year_month . '-' . $last_day . ' -4 day')));

            if ($write_possible_date < $now_date) {
                $is_write = true;
            } else {
                $is_write = false;
                $write_not_possible = true;
            }

            unset($last_day);
            unset($write_possible_date);
            unset($now_date);
        }

        if (!$is_write) {
            if ($write_not_possible === true) {
                $result = Arr::add($result, 'result', 'fail');
                $result = Arr::add($result, 'error', '작성기간이 아닙니다.');
                $result = Arr::add($result, 'errorResult', 'Y');
            } else {
                $result = Arr::add($result, 'result', 'success');
                $result = Arr::add($result, 'error', '모든 학생의 가정통신문이 발송되었습니다.');
                $result = Arr::add($result, 'errorResult', 'Y');
            }
            return response()->json($result);
        }

        $students = RaonMember::where('midx', $user->idx)
            ->where('mtype', 's')
            ->where('s_status', 'Y')
            ->whereRaw("`id` NOT IN (
                SELECT `sidx` FROM advice_notes
                WHERE advice_notes.type = '{$type}' and advice_notes.midx = {$user->idx} AND advice_notes.deleted_at IS NULL
                    AND advice_notes.year = '{$year}' AND advice_notes.month = '{$month}'
                )")
            ->get();

        if (!$students->count()) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '학생 정보가 없습니다.');
            $result = Arr::add($result, 'errorResult', 'Y');
            return response()->json($result);
        }

        $params = array(
            'type' => $type,
            'user' => $user,
            'title' => $title,
            'content' => $content,
            'class_content' => $class_content,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'this_month' => $this_month,
            'next_month' => $next_month,
            'status' => 'Y',
            'batch' => 'Y'
        );

        $students->map(function($student) use($params, $request) {
            $payload = [
                'type' => $params['type'],
                'hidx' => $params['user']->hidx,
                'midx' => $params['user']->midx,
                'sidx' => $student->idx,
                'title' => $params['title'],
                'content' => $params['content'],
                'class_content' => $params['class_content'],
                'year' => $params['year'],
                'month' => $params['month'],
                'day' => $params['day'],
                'this_month' => $params['this_month'],
                'next_month' => $params['next_month'],
                'status' => 'Y',
                'batch' => $params['batch']
            ];

            $adviceNote = new AdviceNote($payload);
            $adviceNote->save();

            $upload_files = null;
            if ($adviceNote) {
                $upload_files = $request->file('upload_files');
            }

            if ($upload_files) {
                $vimeo = new VimeoController();

                foreach ($upload_files as $file) {
                    $file_name = $file->getClientOriginalName();
                    $vimeo_id = null;

                    if (Str::startsWith($file->getMimeType(), 'video')) {
                        $vimeo_id = $vimeo->upload_simple($file);
                    }

                    if ($vimeo_id) {
                        $file_path = AppendFile::getVimeoThumbnailUrl($vimeo_id);
                    } else {
                        $file_path = Storage::disk('s3')->put(AdviceFile::FILE_DIR, $file);
                        //$local_file_path = Storage::disk('public')->putFile(AdviceFile::FILE_DIR, $file);
                    }

                    $adviceNote->files()->create(
                        array(
                            'advice_note_id' => $adviceNote->id,
                            'file_name' => $file_name,
                            'file_path' => $file_path,
                            'file_size' => $file->getSize(),
                            'file_mimetype' => $file->getMimeType(),
                            'vimeo_id' => $vimeo_id
                        )
                    );
                } // foreach End
            }

//            $push = new PushMessageController($adviceNote->type, $adviceNote->id);
//            $push->push();
            BatchPush::dispatch(['type' => $adviceNote->type, 'type_id' => $adviceNote->id, 'param' => []]);
        });

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '등록 되었습니다.');

        return response()->json($result);
    }

    public function storeAllCheck(Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        RequestLog::create(
            [
                'user' => $user_id,
                'request_url' => URL::current(),
                'request_data' => json_encode($request->all()),
                'request_file' => ''
            ]
        );

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

        $type = AdviceNote::LETTER_TYPE;

        $now = Carbon::now();
        $year = $request->input('year') ? $request->input('year') : $now->format('Y');
        $month = $request->input('month') ? $request->input('month') : $now->format('m');

        $this_date = Carbon::create($year, $month);
        $this_month = $this_date->format('Y-m');

        $debug_write = $request->input('debug_write') ? true : false;
        if (env('APP_ENV') != 'development') $debug_write = false;

        $batch_exec = 'N';
        $write_not_possible = false;

        $studentCount = RaonMember::where('midx', $user->idx)
            ->where('mtype', 's')
            ->where('s_status', 'Y')
            ->count();

        $advice_note_count = AdviceNote::where('type', $type)
            ->where('midx', $user->idx)
            ->where('this_month', $this_month)
            ->where('status', 'Y')
            ->count();

        if ($advice_note_count < $studentCount) {
            // @2021-10-01 마지막주만 작성가능
            $now_year_month = date('Y-m');
            if ($debug_write) $now_year_month = $year."-".$month;

            $last_day = date('t', strtotime($now_year_month . '-01'));
            $write_possible_date = strtotime(date('Y-m-d 00:00:00', strtotime($now_year_month . '-' . $last_day . ' -5 day')));
            $now_date = strtotime(date('Y-m-d H:i:s', time()));

            if ($debug_write) $now_date = strtotime(date('Y-m-d 00:00:00', strtotime($now_year_month . '-' . $last_day . ' -4 day')));

            if ($write_possible_date < $now_date) {
                $batch_exec = 'Y';
            } else {
                $batch_exec = 'N';
                $write_not_possible = true;
            }

            unset($last_day);
            unset($write_possible_date);
            unset($now_date);
        }

        if ($batch_exec == 'N') {
            if ($write_not_possible === true) {
                $result = Arr::add($result, 'result', 'fail');
                $result = Arr::add($result, 'error', '작성기간이 아닙니다.');
            } else {
                $result = Arr::add($result, 'result', 'fail');
                $result = Arr::add($result, 'error', '모든 학생의 가정통신문이 발송되었습니다.');
            }
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '가정통신문 전체발송 가능합니다.');

        return response()->json($result);
    }

    public function destroy($adviceNote_id, Request $request)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        RequestLog::create(
            [
                'user' => $user_id,
                'request_url' => URL::current(),
                'request_data' => json_encode($request->all()),
                'request_file' => json_encode([])
            ]
        );

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->mtype, ['a','m'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        if ($user->mtype === 'a') {
            $adviceNote = AdviceNote::with('files')->whereId($adviceNote_id)->first();
        } else {
            $adviceNote = AdviceNote::with('files')->whereId($adviceNote_id)->where('midx', $user->idx)->first();
        }

        if (empty($adviceNote)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            return response()->json($result);
        }

        //파일삭제
        if ($adviceNote->files->count()) {
            $vimeo = new VimeoController();
            foreach ($adviceNote->files as $file_index => $file) {
                if ($file->vimeo_id) {
                    $rs = $vimeo->delete2($file->vimeo_id);
//                    $result = Arr::add($rs, "file.{$file_index}.delete", json_encode($rs));
                } else {
                    $rs = \App::make('helper')->deleteImage($file->file_path);
//                    $result = Arr::add($rs, "file.{$file_index}.delete", $rs);
                }
                $file->forceDelete();
            }
        }

        $adviceComments = AdviceComment::where('advice_note_id','=',$adviceNote_id)->get();
        if ($adviceComments->count() > 0) {
            foreach ($adviceComments as $adviceComment) {
                $adviceComment->forceDelete();
            }
        }

        $adviceNoteHistories = AdviceNoteHistory::where('advice_note_id','=',$adviceNote_id)->get();
        if ($adviceNoteHistories->count() > 0) {
            foreach ($adviceNoteHistories as $adviceNoteHistory) {
                $adviceNoteHistory->forceDelete();
            }
        }

        $adviceNote->forceDelete();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '삭제 되었습니다.');

        return response()->json($result);
    }

    public function fileDelete(Request $request, $file_id)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        $file = AdviceFile::find($file_id);
        if (empty($file)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 파일이 없습니다.');
            return response()->json($result);
        }

        $adviceNote = AdviceNote::whereMidx($user->idx)->whereId($file->advice_note_id)->first();
        if (empty($adviceNote)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        if ($file->vimeo_id) {
            $vimeo = new VimeoController();
            $rs = $vimeo->delete2($file->vimeo_id);
//                    $result = Arr::add($rs, "file.{$file_index}.delete", json_encode($rs));
        } else {
            $rs = \App::make('helper')->deleteImage($file->file_path);
//                    $result = Arr::add($rs, "file.{$file_index}.delete", $rs);
        }
        $file->delete();
        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '삭제 되었습니다.');

        return response()->json($result);
    }

    public function testLetterDeleteAll(Request $request)
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

        $type = AdviceNote::LETTER_TYPE;

        $now = Carbon::now();
        $year = $request->input('year') ? $request->input('year') : $now->format('Y');
        $month = $request->input('month') ? $request->input('month') : $now->format('m');

        $this_date = Carbon::create($year, $month);
        $this_month = $this_date->format('Y-m');

        $advice_note_count = AdviceNote::where('type', $type)
            ->where('midx', $user_id)
            ->where('this_month', $this_month)
            ->where('status', 'Y')
            ->where('batch', 'Y')
            ->count();

        if ($advice_note_count && env('APP_ENV') == 'development') {
            AdviceNote::where('type', $type)
                ->where('midx', $user_id)
                ->where('this_month', $this_month)
                ->where('status', 'Y')
                ->where('batch', 'Y')
                ->forceDelete();

            $result = Arr::add($result, 'result', 'success');
            $result = Arr::add($result, 'error', 'TEST : ' . $this_month . ' 가정통신문이 전체발송 내역이 삭제 되었습니다.');
        } else {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', 'TEST : ' . $this_month . ' 가정통신문 전체발송 내역이 존재하지 않습니다.');
        }

        return response()->json($result);
    }

    public function advice(Request $request)
    {
        $ym = $request->input('ym') ?? date('Y-m');

        $search_text = $request->input('search_text') ?? '';
        $search_user_id = $request->input('search_user_id') ?? '';

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

        //전체 학생리스트
        $req = Request::create('/api/children', 'GET', [
            'user' => $user,
        ]);

        $userController = new UserController();
        $res = $userController->children($req);
        $student = $res->original['list'] ?? [];

        $studentList = [];
        if (count($student) > 0) {
            $studentList[] = [
                'idx' => "",
                'name' => "전체",
            ];
            foreach ($student as $l) {
                $studentList[] = [
                    'idx' => $l['id'],
                    'name' => $l['name'],
                ];
            }
        }

        if ($search_user_id == "") {
            //학생리스트
            $req = Request::create('/adviceNote/student/list', 'GET', [
                'user' => $user,
                'year' => $year,
                'month' => $month,
                'search_user_id' => $search_user_id,
            ]);
            $res = $this->student($req);
            $list = $res->original['list'] ?? [];
            $count = $res->original['count'] ?? 0;
            $letter_count = $res->original['letter_count'] ?? 0;
        } else {
            //알림장 가정통신문 리스트
            $req = Request::create('/adviceNote/list', 'GET', [
                'user' => $search_user_id,
                'year' => $year,
                'month' => $month,
            ]);
            $res = $this->index($req);
            $list = $res->original['list'] ?? [];
        }

        //가정통신문 작성여부 확인
        $req = Request::create('/adviceNote/checkLetter', 'GET', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
        ]);
        $res = $this->checkLetter($req);
        $writeMode = $res->original['write_possible'];

        return view('advice/advice', [
            'user' => $user,
            'list' => $list,
            'count' => $count ?? "",
            'letter_count' => $letter_count ?? "",
            'ym' => $ym,
            'search_text' => $search_text,
            'search_user_id' => $search_user_id,
            'studentList' => json_encode($studentList),
            'writeMode' => $writeMode,
        ]);
    }

    public function adviceList(Request $request)
    {
        $ym = $request->input('ym') ?? date('Y-m');
        $search_text = $request->input('search_text') ?? '';

        $year = $month = "";
        if ($ym != '') {
            $ymArr = explode('-', $ym);
            $year = $ymArr[0] ?? '';
            $month = $ymArr[1] ?? '';
        }

        $user = \App::make('helper')->getUsertId();

        //알림장 가정통신문 리스트
        $req = Request::create('/adviceNote/list', 'GET', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'search_text' => $search_text,
        ]);
        $res = $this->index($req);
        $list = $res->original['list'] ?? [];

        return view('advice/list',[
            'search_text' => $search_text,
            'list' => $list,
            'ym' => $ym,
            'user' => $user,
        ]);
    }

    public function noteView(Request $request, $user_id, $id)
    {
        $isCopy = $request->input('iscopy');

        if ($isCopy) {
            $adviceNoteShareHistory = AdviceNoteShareHistory::where('advice_note_id', $id)->first();

            if ($adviceNoteShareHistory) {
                $adviceNoteShareHistory->confirmed_at = date('Y-m-d H:i:s', time());

                $adviceNoteShareHistory->save();
            }
        }

        // 알람 통해서 이동했는데 다른 자녀일 경우 홈으로
        if (session()->get('auth')['user_type'] == 's') {
            if (session()->get('auth')['user_id'] != $user_id) {
                return redirect('/');
            }
        }

        $user = $user_id;
        $userType = \App::make('helper')->getUsertType();

//        \App::make('helper')->vardump($userType);

        if (in_array($userType, ['m'])) {
            $user = \App::make('helper')->getUsertId();
        }

        $req = Request::create('/api/adviceNote/view/'.$id, 'GET', [
            'user' => $user,
        ]);
        $res = $this->show($req, $id);
        // \App::make('helper')->vardump($res->original);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }
//        \App::make('helper')->vardump( $res->original);
        return view('advice/noteView',[
            'row' => $res->original ?? [],
            'id' => $id,
        ]);
    }

    public function noteWrite(Request $request, $id="")
    {
        $ymd = date('Y-m-d');
        $mode = "w";

        $ym = $request->input('ym') ?? '';
        $search_user_id = $request->input('search_user_id') ?? '';

        if ($ym != "" && $ym != date('Y-m')) {
            $ymd = $ym."-01";
        }

        $user = \App::make('helper')->getUsertId();
        $userType = \App::make('helper')->getUsertType();
        if (in_array($userType, ['a','h'])) {
            $user = session()->get('center');
        }

        $row = [];
        if ($id != "") {
            $mode = "u";

            $req = Request::create('/api/adviceNote/view/'.$id, 'GET', [
                'user' => $user,
                'modify' => 1,
            ]);
            $res = $this->show($req, $id);

            if ($res->original['result'] != 'success') {
                $error = \App::make('helper')->getErrorMsg($res->original['error']);
                \App::make('helper')->alert($error);
            }

            $row = $res->original ?? [];
            if (isset($row['date2']) && $row['date2'] != "") {
                $ymd = $row['date2'];
            }
        } else {
            if ($search_user_id) {
                $req = Request::create('/api/selectChild', 'GET', [
                    'user' => $search_user_id,
                ]);

                $userController = new UserController();
                $res = $userController->selectChild($req);
                $student = $res->original['list'] ?? [];
            } else {
                //전체 학생리스트
                $req = Request::create('/api/children', 'GET', [
                    'user' => $user,
                ]);
                $userController = new UserController();
                $res = $userController->children($req);
                $student = $res->original['list'] ?? [];
            }
        }

        return view('advice/noteWrite', [
            'ymd' => $ymd,
            'student' => $student ?? "",
            'mode' => $mode,
            'id' => $id,
            'row' => $row,
            'search_user_id' => $search_user_id,
        ]);
    }

    public function letterView(Request $request, $user_id, $id)
    {
        $isCopy = $request->input('iscopy');

        if ($isCopy) {
            $adviceNoteShareHistory = AdviceNoteShareHistory::where('advice_note_id', $id)->first();

            if ($adviceNoteShareHistory) {
                $adviceNoteShareHistory->confirmed_at = date('Y-m-d H:i:s', time());

                $adviceNoteShareHistory->save();
            }
        }

        // 알람 통해서 이동했는데 다른 자녀일 경우 홈으로
        if (session()->get('auth')['user_type'] == 's') {
            if (session()->get('auth')['user_id'] != $user_id) {
                return redirect('/');
            }
        }

        $user = $user_id;
        $userType = \App::make('helper')->getUsertType();
        // \App::make('helper')->vardump($userType);
        if (in_array($userType, ['m'])) {
            $user = \App::make('helper')->getUsertId();
        }

        $req = Request::create('/api/adviceNote/view/'.$id, 'GET', [
            'user' => $user
        ]);
        $res = $this->show($req, $id);

        // \App::make('helper')->vardump($res->original);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        return view('advice/letterView',[
            'row' => $res->original ?? [],
            'userId' => $user_id,
            'id' => $id,
        ]);
    }

    public function letterWrite(Request $request, $id="")
    {
        // $id 가 있으면 편집 모드
        // 없으면 쓰기 모드인데 날짜로 조회해서 값이 있으면 편집 모드
        $ymd = date('Y-m-d');
        $mode = "w";

        $ym = $request->input('ym') ?? '';
        $search_user_id = $request->input('search_user_id') ?? '';
        $userId = $request->input('userId') ?? '';

        if ($ym != "" && $ym != date('Y-m')) {
            $ymd = $ym."-01";
        }

        $user = \App::make('helper')->getUsertId();
        $userType = \App::make('helper')->getUsertType();
        if (in_array($userType, ['a','h'])) {
            $user = session()->get('center');
        }

        $nextMonth = Carbon::now()->addMonthNoOverflow()->format('Y-m');
        $minMonth = '2020-02';

        $row = [];
        // id가 있으니 편집 모드
        if ($id != "") {
            $mode = "u";

            $req = Request::create('/api/adviceNote/view/'.$id, 'GET', [
                'user' => $user,
            ]);
            $res = $this->show($req, $id);

            if ($res->original['result'] != 'success') {
                $error = \App::make('helper')->getErrorMsg($res->original['error']);
                \App::make('helper')->alert($error);
            }

            $row = $res->original ?? [];
            if (isset($row['date2']) && $row['date2'] != "") {
                $ymd = $row['date2'];
                $ym = $row['ym'];
            }

            //전체 학생리스트
            $req = Request::create('/adviceNote/student/list', 'GET', [
                'user' => $user,
                'year' => $ymdArr[0] ?? "",
                'month' => $ymdArr[1] ?? "",
                'search_user_id' => $userId ?? "",
            ]);
            $res = $this->student($req);
            $student = $res->original['list'] ?? [];
        } else {
            // 이번 달이 아니면 가정통신문 작성 불가
            $nowDate = date('Y-m');

            if ($userType != 'a' && $nowDate != $ym) {
                $error = \App::make('helper')->getErrorMsg("이번 달에 해당하는 가정통신문만 작성할 수 있습니다.");
                \App::make('helper')->alert($error);
            }

            // 날짜로 조회
            $ymdArr = explode('-',$ymd);
            if ($userType == 'a' || $mode == 'w') {
                if ($userType == 'a') {
                    $mode = 'a';
                }

                $req = Request::create('/adviceNoteAdmin/write/'.$id, 'GET', [
                    'user' => \App::make('helper')->getUsertId(),
                    'year' => $ymdArr[0] ?? "",
                    'month' => $ymdArr[1] ?? "",
                ]);
                $adviceNoteAdminController = new AdviceNoteAdminController();
                $res = $adviceNoteAdminController->show($req);

                if ($res->original['result'] == 'success') {
//                    $mode = "u";
                    $row['prefix_content'] = $res->original['prefix_content'];
                    $row['this_month_education_info'] = $res->original['this_month_education_info'];
                    $nextMonth = $res->original['nextMonth'];
                    $minMonth = $res->original['minMonth'];
                    $ymd = $res->original['date'];
                }
            }

            //전체 학생리스트
            $req = Request::create('/adviceNote/student/list', 'GET', [
                'user' => $user,
                'year' => $ymdArr[0] ?? "",
                'month' => $ymdArr[1] ?? "",
                'search_user_id' => $search_user_id ?? "",
            ]);
            $res = $this->student($req);

            // 가정통신문 발송한 학생은 제외
            foreach ($res->original['list'] as $key => $list) {
                if ($list['letter'] > 0) {
                    unset($res->original['list'][$key]);
                }
            }
//            $req = Request::create('/api/children', 'GET', [
//                'user' => $user,
//            ]);
//            $userController = new UserController();
//            $res = $userController->children($req);
            $student = $res->original['list'] ?? [];
            $student_cnt = count($student);
            $letter_cnt = 0;
            $search_user_is_letter = false;
            $search_user_letter_name = "";
            if($student_cnt > 0) {
                foreach($student as $l) {
                    if($l['letter'] == "1") $letter_cnt++;
                    if($search_user_id == $l['id'] && $l['letter'] == "1") {
                        $search_user_is_letter = true;
                        $search_user_letter_name = $l['name'];
                    }
                }
            }
//            if ($student_cnt == $letter_cnt && $letter_cnt > 0) {
//                \App::make('helper')->alert("가정통신문 발송이 완료 되었습니다.");
//            }

            if ($search_user_is_letter) {
                $smonth = $ymdArr[1] ?? "";
                $smonth = $smonth ? sprintf('%02d', $smonth) : date("m");
//                \App::make('helper')->vardump($student);
                \App::make('helper')->alert($search_user_letter_name."회원의 ".$smonth."월 가정통신문이 이미 발송 되었습니다.");
            }

            if (count($student) == 0 && $userType !== 'a') {
                \App::make('helper')->alert("모든 학생에게 가정통신문을 발송하여 더이상 가정통신문 작성을 할 수 없습니다.");
            }
        }

        // 작성 후 수정되었습니다 -> 모든 학생에게 가정통신문을 발송하여 더이상 가정통신문 작성을 할 수 없습니다.

        return view('advice/letterWrite', [
            'ym' => $ym,
            'ymd' => $ymd,
            'nextMonth' => $nextMonth,
            'minMonth' => $minMonth,
            'student' => $student ?? [],
            'mode' => $mode,
            'id' => $id,
            'row' => $row,
            'search_user_id' => $search_user_id,
            'userId' => $userId
        ]);
    }

    public function writeAction(Request $request)
    {
        $mode = $request->input('mode') ?? '';
        $id = $request->input('id') ?? '';
        $userId = $request->input('userId') ?? '';
        $type = $request->input('type') ?? '';
        $ymd = $request->input('ymd') ?? '';
        $content = $request->input('content') ?? '';
        $student = $request->input('student') ?? '';
        $upload_files = $request->file('upload_files');
        $tmp_file_ids = $request->file('tmp_file_ids');
        $prefix_content = $request->input('prefix_content') ?? '';
        $this_month_education_info = $request->input('this_month_education_info') ?? '';
        $multiform_delete_idx = $request->input('multiform_delete_idx') ?? '';
        $multiform_idx = $request->input('multiform_idx') ?? '';

        if ($mode != 'a' && session('auth')['user_type'] =='m') {
            if ($ymd == "") \App::make('helper')->alert('작성일자를 입력해주세요.');
            $ymdArr = explode('-', $ymd);
            $year = $ymdArr[0]??-1;
            $month = $ymdArr[1]??-1;
            $day = $ymdArr[2]??1;
//            if (! checkdate((int)$month,(int)$day,(int)$year)) \App::make('helper')->alert('올바른 작성일자가 아닙니다.');
            if ($content == "" && $type != "letter") \App::make('helper')->alert('내용을 입력해주세요.');
//            if (!$upload_files) \App::make('helper')->alert('사진·동영상을 등록해주세요.');
            if ($student == "") \App::make('helper')->alert('학생을 선택해 주세요.');
        } else {
            if ($prefix_content == "") \App::make('helper')->alert('내용을 입력해주세요.');
            if ($this_month_education_info == "") \App::make('helper')->alert('교육정보를 입력해주세요.');
        }

        $user = \App::make('helper')->getUsertId();
        $userType = \App::make('helper')->getUsertType();
        if (in_array($userType, ['a','h'])) {
            $user = session()->get('center');
        }

//        \App::make('helper')->vardump($upload_files);

//        $multiform_idx_arr = [];
//        if ($multiform_idx != "") {
//            $multiform_idx_arr = explode(",", $multiform_idx);
//        }
//        \App::make('helper')->vardump($multiform_idx_arr);


//        \App::make('helper')->vardump($multiform_delete_idx);
//        $delete_upload_keys = [];
//        if ($multiform_delete_idx != "") {
//            $multiform_delete_idx_arr = explode(",", $multiform_delete_idx);
//            \App::make('helper')->vardump($multiform_delete_idx_arr);
//            if (is_array($multiform_idx_arr) && count($multiform_idx_arr) > 0) {
//                foreach ($multiform_idx_arr as $k => $l) {
//                    if (is_array($multiform_delete_idx_arr) && in_array($l, $multiform_delete_idx_arr)) {
//                        $delete_upload_keys[] = $k;
//                    }
//                }
//            }
//        }
//
//        \App::make('helper')->vardump($delete_upload_keys);
//        return;

        // 1. $mode = 'w' + $type = 'advice' -> 알림장 작성
        // 2. $mode = 'u' + $type = 'advice' -> 알림장 수정

        // 3. $mode = 'a' + $type = 'letter' -> 관리자 가정통신문 작성
        // 3. $mode = 'w' + $type = 'letter' -> 원장 가정통신문 작성
        // 4. $mode = 'u' + $type = 'letter' -> 관리자 가정통신문 수정

        // 알림장 수정
        if ($mode == 'u' && $type == 'advice') {
            //파일 삭제
            $delete_ids = $request->input('delete_ids') ?? '';
            if ($delete_ids != "") {
                $deleteIdsArr = explode(',', $delete_ids);
                if (is_array($deleteIdsArr) && count($deleteIdsArr) > 0) {
                    $req = Request::create('/adviceNote/fileDelete/565707', 'GET', [
                        'user' => $user,
                    ]);
                    foreach ($deleteIdsArr as $l) {
                        $res = $this->fileDelete($req, $l);
                    }
                }
            }

            $request->merge([
                'user' => $user,
                'student' => $student,
                'type' => $type,
            ]);
            $res = $this->update($request, $id);
        // 가정통신문 작성
        } else if ($mode == 'a') {
            $request->merge([
                'user' => \App::make('helper')->getUsertId(),
            ]);
            $adviceNoteAdminController = new AdviceNoteAdminController();
            $res = $adviceNoteAdminController->store($request);
        } else if ($mode == 'u' && $type = 'letter') {
            // 어드민 가정통신문 수정
            if (session('auth')['user_type'] == 'a') {
                $request->merge([
                    'user' => \App::make('helper')->getUsertId(),
                ]);
                $adviceNoteAdminController = new AdviceNoteAdminController();
                $res = $adviceNoteAdminController->store($request);
            }
        // 원장 가정통신문 작성
        } else if ($mode == 'w' && $type == 'letter') {
            $request->merge([
                'user' => \App::make('helper')->getUsertId(),
            ]);

            $res = $this->store($request);
        // 알림장 작성
        } else {
            $requestMergeData = [
                'user' => $user,
                'year' => $year ?? "",
                'month' => $month ?? "",
                'day' => $day ?? "",
                'type' => $type,
            ];

            $tmpFileIds = $request->input('tmp_file_ids');
            // 임시저장 폴더 request당 폴더를 만들고 api 리퀘스트가 끝나면 폴더를 삭제하여 임시파일을 삭제한다.
            $tmpSaveFilePath = 'tmp/'.Str::uuid()."/";

            // 임시파일 저장이라면 s3에서 데이터를 꺼내와 uploaded 파일 객체로 만들어 준 후
            // request에 업로드 파일을 merge하도록 한다.
            if ($tmpFileIds != "") {
                $tmpFileIdArr = explode(",",$tmpFileIds);
                $fileDatas = File::whereIn('id',$tmpFileIdArr)->get();
                if ($fileDatas) {
                    foreach ($fileDatas as $fileData) {
                        $content = \App::make('helper')->getDownloadImage($fileData->file_path);
                        $tmpPath = $tmpSaveFilePath . $fileData->file_name;
                        Storage::disk('local')->put($tmpPath, $content);
                        $storagePath  = Storage::disk('local')->path($tmpPath);
                        $upFile = new UploadedFile($storagePath, $fileData->file_name);
                        // $request->files->set('file', $upFile);
                        $requestMergeData['upload_files'][] = $upFile;
                    }
                }
            }

            $request->merge($requestMergeData);

            $res = $this->store($request);

            //임시파일 삭제
            $storagePath  = Storage::disk('local')->path($tmpSaveFilePath);
            @array_map('unlink', glob("$storagePath/*.*"));
            @rmdir($storagePath);
        }

//        \App::make('helper')->vardump($request->all());
//        \App::make('helper')->vardump($res->original);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        // 가정통신문 작성
        if ($mode == 'a') {
            \App::make('helper')->alert("가정통신문 작성이 완료 되었습니다.");
        } else {
            $typelink = ($type == 'advice') ? "note":"letter";
            $link = "/advice";
            if ($mode == 'u') {
                // 가정통신문 수정
                if ($type == 'letter') {
                    if ($userId && $id) {
                        $link = "/advice/letter/write/".$id.'?userId='.$userId;
                    } elseif ($id) {
                        $link = "/advice/letter/write/".$id;
                    } else {
                        $link = "/advice/letter/write?ym=".$ymd;
                    }
                // 알림장 수정
                } else {
                    $link = "/advice/".$student."/".$typelink."/view/".$id;
                }
            // 알림장 등록
            } else {
                if ($type == 'letter') {
                    //전체 학생리스트
                    $req = Request::create('/adviceNote/student/list', 'GET', [
                        'user' => $user,
                        'year' => $ymdArr[0] ?? "",
                        'month' => $ymdArr[1] ?? "",
                        'search_user_id' => "",
                    ]);
                    $res = $this->student($req);
                    $student = $res->original['list'] ?? [];
                    $student_cnt = count($student);
                    $letter_cnt = 0;
                    if($student_cnt > 0) {
                        foreach($student as $l) {
                            if($l['letter'] == "1") $letter_cnt++;
                        }
                    }
                    if ($student_cnt == $letter_cnt && $letter_cnt > 0) {
                        \App::make('helper')->alert("가정통신문이 발송 되었습니다.",$link);
                    }
                }
            }
            \App::make('helper')->alert( (($mode == 'u')?"수정":"등록")."되었습니다.", $link);
        }
    }

    public function adviceDelete($id)
    {
        $user = \App::make('helper')->getUsertId();
        $req = Request::create('/adviceNote/delete/'.$id, 'POST', [
            'user' => $user,
        ]);
        $res = $this->destroy($id, $req);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        \App::make('helper')->alert("삭제되었습니다.", "/advice");
    }

    public function downloadFile(Request $request, $id)
    {
        $file = AdviceFile::whereId($id)->first();
        if (empty($file)) {
            echo "잘못된 접근입니다.";
            exit;
        }

        if ($file->vimeo_id) {
            echo "잘못된 접근입니다.";
            exit;
        } else {
            $file_name = $file->file_name;
            $headers = [
                'Content-Type'        => 'application/png',
                'Content-Disposition' => 'attachment; filename="'. $file_name .'"',
            ];

            return \Response::make(\App::make('helper')->getDownloadImage($file->file_path), 200, $headers);
        }
    }

}
