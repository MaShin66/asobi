<?php

namespace App\Http\Controllers;

use App\AppendFile;
use App\CommonComment;
use App\Jobs\BatchPush;
use App\Notice;
use App\NoticeFile;
use App\NoticeHistory;
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
use Validator;
use App\Rules\UploadFile;
use App\File;
use \Illuminate\Http\UploadedFile;

class NoticeController extends Controller
{
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

        if (!in_array($user->mtype, ['m','s'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $now = Carbon::now();
        $year = $request->input('year') ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d', $request->input('month')) : $now->format('m');
        $day = $request->input('day') ? sprintf('%02d', $request->input('day')) : null;
        $search_text = $request->input('search_text') ?? '';
        $search_text = trim($search_text);

        if (in_array($user->mtype, ['a'])) {
            $rs = Notice::with('files')
                ->where('writer_type', 'a')
                ->when($search_text != "", function ($q) use ($search_text) {
                    return $q->whereRaw('(title like ? or content like ?)',['%'.$search_text.'%','%'.$search_text.'%']);
                })
                ->orderByDesc('created_at')->get();
        } else if (in_array($user->mtype, ['h','m'])) {
            $writer_type = $request->input('type');
            $rs = Notice::with('files')
                ->where('status', 'Y')
                ->where('view_type', 'like', "%" . json_encode($user->mtype) . "%")
                ->when($user->mtype === 'm', function($query) use($user) {
                    return $query->whereIn('hidx', [0, $user->hidx])
                        ->whereIn('midx', [0, $user->idx]);
                })
                ->when($writer_type, function($query) use($writer_type) {
                    if ($writer_type == "a") {
                        $query->whereRaw(' (writer_type = "a" or writer_type = "admin") ');
                    } else {
                        $query->where('writer_type', $writer_type);
                    }
                })
                ->when($user->mtype === 'h', function($query) use($user) {
                    return $query->whereIn('hidx', [0, $user->idx]);
                })
                ->when($year, function($query) use($year) {
                    return $query->where('year', $year);
                })
                ->when($month, function($query) use($month) {
                    return $query->where('month', $month);
                })
                ->when($day, function($query) use($day) {
                    return $query->where('day', $day);
                })
                ->when($search_text != "", function ($q) use ($search_text) {
                    return $q->whereRaw('(title like ? or content like ?)',['%'.$search_text.'%','%'.$search_text.'%']);
                })
                ->orderByDesc('created_at')
                ->get();
        } else if (in_array($user->mtype, ['s'])) {
            $writer_type = $request->input('type');
            $rs = Notice::with('files')
                ->where('status', 'Y')
                ->whereIn('midx', [$user->midx, 0])
                ->where('view_type', 'like', "%" . json_encode($user->mtype) . "%")
                ->when($writer_type, function($query) use($writer_type) {
                    if ($writer_type == "a") {
                        $query->whereRaw(' (writer_type = "a" or writer_type = "admin") ');
                    } else {
                        $query->where('writer_type', $writer_type);
                    }
                })
                ->when($year, function($query) use($year) {
                    return $query->where('year', $year);
                })
                ->when($month, function($query) use($month) {
                    return $query->where('month', $month);
                })
                ->when($day, function($query) use($day) {
                    return $query->where('day', $day);
                })
                ->when($search_text != "", function ($q) use ($search_text) {
                    return $q->whereRaw('(title like ? or content like ?)',['%'.$search_text.'%','%'.$search_text.'%']);
                })
                ->orderByDesc('created_at')
                ->get();
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            $index = 0;
            foreach ($rs as $row) {
                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.title", $row->title);
                $result = Arr::add($result, "list.{$index}.content", $row->content);
                $this_date = Carbon::create($row->year, $row->month, $row->day);
//                $result = Arr::add($result, "list.{$index}.date", $this_date->format(Notice::DATE_FORMAT));
                $result = Arr::add($result, "list.{$index}.date", $this_date->format('Y.m.d')." ".\App::make('helper')->dayOfKo($this_date, 2));
                $result = Arr::add($result, "list.{$index}.reg_date", $row->created_at->format(Notice::REG_DATE_FORMAT));
                $result = Arr::add($result, "list.{$index}.writer_type", $row->writer_type);
                $result = Arr::add($result, "list.{$index}.user_id", $row->user_id);
                $result = Arr::add($result, "list.{$index}.type", ($row->user_id == "1")?"본사":"교육원");

                if ($row->files->count()) {
                    foreach ($row->files as $file_index => $file) {
                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_id", $file->id);
                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_name", $file->file_name);
//                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_path", $file->vimeo_id ? AppendFile::getVimeoThumbnailUrl($file->vimeo_id) : \App::make('helper')->getImage($file->file_path));
                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.file_path", $file->vimeo_id ? null : \App::make('helper')->getImage($file->file_path));
                        $result = Arr::add($result, "list.{$index}.file.{$file_index}.vimeo_id", $file->vimeo_id ? $file->vimeo_id : null);
                    }
                }

                $index++;
            }
        }

        return response()->json($result);
    }

    public function show(Request $request, $notice_id)
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

        $row = Notice::with('files')->whereId($notice_id)->where('status', 'Y')->first();

        if (empty($row)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            return response()->json($result);
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, "id", $row->id);
        $result = Arr::add($result, "title", $row->title);
        $result = Arr::add($result, "content", $row->content);
        $this_date = Carbon::create($row->year, $row->month, $row->day);
        $result = Arr::add($result, "date", $this_date->format('Y.m.d')." ".\App::make('helper')->dayOfKo($this_date, 2));
        $result = Arr::add($result, "reg_date", $row->created_at->format(Notice::REG_DATE_FORMAT));
        $result = Arr::add($result, "writer_type", $row->writer_type);
        $result = Arr::add($result, "user_id", $row->user_id);
        $result = Arr::add($result, "type", ($row->user_id == "1")?"본사":"교육원");

        if ($row->files->count()) {
            foreach ($row->files as $file_index => $file) {
                $result = Arr::add($result, "file.{$file_index}.file_id", $file->id);
                $result = Arr::add($result, "file.{$file_index}.file_name", $file->file_name);
//                $result = Arr::add($result, "file.{$file_index}.file_path", $file->vimeo_id ? AppendFile::getVimeoThumbnailUrl($file->vimeo_id) : \App::make('helper')->getImage($file->file_path));
//                $result = Arr::add($result, "file.{$file_index}.video_path", $file->vimeo_id ? AppendFile::getVimeoUrl($file->vimeo_id) : null);
                $vimeo_file = null;
                if ($modify == "1" && $file->vimeo_id) $vimeo_file = AppendFile::getVimeoThumbnailUrl($file->vimeo_id);
                $result = Arr::add($result, "file.{$file_index}.file_path", $file->vimeo_id ? $vimeo_file : \App::make('helper')->getImage($file->file_path));
                $result = Arr::add($result, "file.{$file_index}.video_id", $file->vimeo_id ? $file->vimeo_id : null);
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

//            $readed_students = NoticeHistory::select(
//                'raon_member.idx',
//                'raon_member.id',
//                'raon_member.name'
//            )
//            ->join('raon_member', 'raon_member.idx', '=', 'notice_histories.sidx')
//            ->where('notice_histories.notice_id', $notice_id)
//            ->where('notice_histories.hidx', $user->hidx)
//            ->where('notice_histories.midx', $user->idx)
//            ->whereNotNull('notice_histories.sidx')
//            ->get();

            $noticeHistorySidx = NoticeHistory::where('notice_histories.notice_id', $notice_id)
                ->where('notice_histories.hidx', $user->hidx)
                ->where('notice_histories.midx', $user->idx)
                ->whereNotNull('notice_histories.sidx')
                ->pluck('sidx')
                ->toArray();

            $readed_students = RaonMember::select('idx', 'id', 'name')->whereIn('idx', $noticeHistorySidx)->get();

            $readed_students_array = [];

            if ($readed_students->count()) {
                $readed_students_array = $readed_students->pluck('idx');
            }

            $not_readed_students = RaonMember::select(
                'idx',
                'id',
                'name'
            )
            ->where('midx', $user->idx)
            ->where('mtype', 's')
            ->where('s_status', 'Y')
            ->when(count($readed_students_array) > 0, function($query) use($readed_students_array) {
                return $query->whereNotIn('idx', $readed_students_array->toArray());
            })
            ->get();

            unset($readed_students_array);

            $result = Arr::add($result, "readed_students", $readed_students);
            $result = Arr::add($result, "not_readed_students", $not_readed_students);
        }

        return response()->json($result);
    }

    public function store(Request $request) {
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

        if (!in_array($user->mtype, ['a','m'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $title = $request->input('title');
        $content = $request->input('content');

        //권한 설정
        // @20200103 지사 공지사항 일 경우 해당 지사의 교육원만 볼 수 있다.
        if ($user->mtype === 'h') {
            $arr_view_type = array('m');
        } else {
            $arr_view_type = array();
            $arr_view_type[0] = "s";
            if ($user->mtype == 'a') {
                $arr_view_type[1] = "m";
                //$arr_view_type[2] = "h";
            } else if ($user->mtype == "m") {
                $arr_view_type[1] = "m";
            }
        }

        $now = Carbon::now();
        $year = $request->input('year') ? sprintf('%04d', $request->input('year')) : $now->format('Y');
        $month = $request->input('month') ? sprintf('%02d', $request->input('month')) : $now->format('m');
        $day = $request->input('day') ? sprintf('%02d', $request->input('day')) : $now->format('d');

        $midx = 0;
        if ($user->mtype === 'm' ) {
            $midx = $user->idx;
        }

        $payload = [
            'hidx' => $user->mtype == 'h' ? $user->idx : $user->hidx,
            'midx' => $midx,
            'writer_type' => $user->mtype,
            'view_type' => json_encode($arr_view_type),
            'title' => $title,
            'content' => $content,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'status' => 'Y',
            'user_id' => $user->idx
        ];
        $notice = new Notice($payload);


        $notice->save();

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
                    $file_path = \App::make('helper')->putResizeS3(NoticeFile::FILE_DIR, $file);
                }

                $notice->files()->create(
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
        $result = Arr::add($result, 'error', '등록 되었습니다.');
        $result = Arr::add($result, 'id', $notice->id);

//        $push = new PushMessageController('notice', $notice->id);
//        $push->push();
        BatchPush::dispatch(['type' => 'notice', 'type_id' => $notice->id, 'param' => []]);

        return response()->json($result);
    }

    public function update(Request $request, $notice_id) {
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

        if (in_array($user->mtype, ['s'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $notice = Notice::whereId($notice_id)->where('status', 'Y')->where('writer_type', $user->mtype)->first();
        if (empty($notice)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            return response()->json($result);
        }

        $title = $request->input('title');
        $content = $request->input('content');

        $payload = [
            'title' => $title,
            'content' => $content
        ];

        $notice->fill($payload);
        $notice->save();

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
                    $file_path = \App::make('helper')->putResizeS3(NoticeFile::FILE_DIR, $file);
                }

                $notice->files()->create(
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

    public function destroy($notice_id, Request $request) {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

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
            $notice = Notice::whereId($notice_id)->first();
        } else {
            $notice = Notice::whereId($notice_id)->where('midx', $user->idx)->first();
        }
        if (empty($notice)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '잘못된 요청입니다.');
            return response()->json($result);
        }

        //파일삭제
        if ($notice->files->count()) {
            $vimeo = new VimeoController();
            foreach ($notice->files as $file_index => $file) {
                if ($file->vimeo_id) {
                    $rs = $vimeo->delete2($file->vimeo_id);
                } else {
                    $rs = \App::make('helper')->deleteImage($file->file_path);
                }
                $file->forceDelete();
            }
        }

        $commonComments = CommonComment::where('type','=','3')->where('type_id','=',$notice_id)->get();
        if ($commonComments->count() > 0) {
            foreach ($commonComments as $commonComment) {
                $commonComment->forceDelete();
            }
        }

        $noticeHistories = NoticeHistory::where('notice_id','=',$notice_id)->get();
        if ($noticeHistories->count() > 0) {
            foreach ($noticeHistories as $noticeHistory) {
                $noticeHistory->forceDelete();
            }
        }

        $notice->forceDelete();

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

        $file = NoticeFile::find($file_id);
        if (empty($file)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 파일이 없습니다.');
            return response()->json($result);
        }

        $notice = Notice::whereId($file->notice_id)->where('status', 'Y')->where('writer_type', $user->mtype)->first();
        if (empty($notice)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        if ($file->vimeo_id) {
            $vimeo = new VimeoController();
            $rs = $vimeo->delete2($file->vimeo_id);
        } else {
            $rs = \App::make('helper')->deleteImage($file->file_path);
        }
        $file->delete();
        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '삭제 되었습니다.');

        return response()->json($result);
    }

    public function notice(Request $request)
    {
        $ym = $request->input('ym') ?? date('Y-m');
        $type = $request->input('type') ?? '';
        $search_text = $request->input('search_text') ?? '';
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

        $req = Request::create('/api/notice/list', 'GET', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'type' => $type,
            'search_text' => $search_text,
        ]);
        $res = $this->index($req);
        $list = $res->original['list'] ?? [];
        // \App::make('helper')->vardump($month);
        // \App::make('helper')->vardump($list);

        return view('notice/list', [
            'list' => $list,
            'ym' => $ym,
            'type' => $type,
            'search_text' => $search_text,
        ]);
    }

    public function noticeView(Request $request, $id)
    {
        $ym = $request->input('ym') ?? date('Y-m');
        $uesrId = \App::make('helper')->getUsertId();
        $userType = \App::make('helper')->getUsertType();
        if (in_array($userType, ['a','h'])) {
            $uesrId = session()->get('center');
        }
        $albumReq = Request::create('/api/notice/view/'.$id, 'GET', [
            'user' => $uesrId,
        ]);
        $res = $this->show($albumReq, $id);
        $student = $res->original['student'] ?? [];

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        return view('notice/view',[
            'row' => $res->original ?? [],
            'modifyBtn' => \App::make('helper')->getUsertId() == $res->original['user_id'],
            'deleteBtn' => \App::make('helper')->getUsertId() == $res->original['user_id'],
            'studentReadN' => $res->original['not_readed_students'] ?? [],
            'studentReadY' => $res->original['readed_students'] ?? [],
            'back_link' => "/notice?ym=".$ym,
            'id' => $id,
        ]);
    }

    public function noticeWrite(Request $request, $id="")
    {
        $ymd = date('Y-m-d');
        $mode = "w";

        $ym = $request->input('ym') ?? '';

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

            $req = Request::create('/api/notice/view/'.$id, 'GET', [
                'user' => $user,
                'modify' => 1,
            ]);
            $res = $this->show($req, $id);

            if ($res->original['result'] != 'success') {
                $error = \App::make('helper')->getErrorMsg($res->original['error']);
                \App::make('helper')->alert($error);
            }

            $row = $res->original ?? [];
            if (isset($row['date']) && $row['date'] != "") {
                $ymd = $row['date'];
            }

            if (isset($row['date']) && $row['date'] != "") {
                $date = explode(' ',$row['date']);
                $date = str_replace('.','-',$date[0]);
                $ymd = $date;
            }
        }

        return view('notice/write',[
            'mode' => $mode,
            'id' => $id,
            'row' => $row,
            'ymd' => $ymd,
        ]);
    }

    public function writeAction(Request $request)
    {
        $mode = $request->input('mode') ?? '';
        $id = $request->input('id') ?? '';
        $date = $request->input('date') ? explode('-', $request->input('date')) : explode('-', date("Y-m-d"));
        $upload_files = $request->file('upload_files');
        $tmp_file_ids = $request->file('tmp_file_ids');

//        \App::make('helper')->vardump($upload_files);
//        exit;

        $user = \App::make('helper')->getUsertId();
        $userType = \App::make('helper')->getUsertType();
        if (in_array($userType, ['a','h'])) {
            $user = session()->get('center');
        }

        $request->merge([
            'user' => \App::make('helper')->getUsertId(),
            'year' => $date[0],
            'month' => $date[1],
            'day' => $date[2],
        ]);

        if ($mode == 'u') {
            //파일 삭제
            $delete_ids = $request->input('delete_ids') ?? '';
            if ($delete_ids != "") {
                $deleteIdsArr = explode(',', $delete_ids);
                if (is_array($deleteIdsArr) && count($deleteIdsArr) > 0) {
                    $req = Request::create('/api/album/fileDelete/'.$id, 'GET', [
                        'user' => $user,
                    ]);
                    foreach ($deleteIdsArr as $l) {
                        $res = $this->fileDelete($req, $l);
                    }
                }
            }
            $res = $this->update($request, $id);
        } else {
            $tmpFileIds = $request->input('tmp_file_ids');
            // 임시저장 폴더 request당 폴더를 만들고 api 리퀘스트가 끝나면 폴더를 삭제하여 임시파일을 삭제한다.
            $tmpSaveFilePath = 'tmp/'.Str::uuid()."/";

            // 임시파일 저장이라면 s3에서 데이터를 꺼내와 uploaded 파일 객체로 만들어 준 후
            // request에 업로드 파일을 merge하도록 한다.
            if ($tmpFileIds != "") {
                $requestMergeData = [];
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
                $request->merge($requestMergeData);
            }

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

        $mode = $request->input('mode') ?? '';

        $link = "/notice?ym=".$date[0].'-'.$date[1];
        if ($mode == 'u') $link = "/notice/view/".$id;
        \App::make('helper')->alert( (($mode == 'u')?"수정":"등록")."되었습니다.", $link);
    }

    public function noticeDelete($id)
    {
        $user = \App::make('helper')->getUsertId();
        $req = Request::create('/notice/delete/'.$id, 'POST', [
            'user' => $user,
        ]);
        $res = $this->destroy($id, $req);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        \App::make('helper')->alert("삭제되었습니다.", "/notice");
    }

    public function downloadFile(Request $request, $id)
    {
        $file = NoticeFile::whereId($id)->first();
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
