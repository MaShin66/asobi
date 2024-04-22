<?php

namespace App\Http\Controllers;

use App\AppendFile;
use App\CommonHistory;
use App\Rules\UploadFile;
use App\Models\RaonMember;
use App\EducatonInfo;
use App\CommonComment;
use App\EditorFile;
use App\File;
use App\UserAppInfo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Validator;
use App\Jobs\BatchPush;

class EducatonInfoController extends Controller
{

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

        if (!in_array($user->mtype, ['a'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $validator = Validator::make($request->all(), [
            'upload_files' => [new UploadFile],
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => "업로드 하려는 파일은 이미지만 가능하고 이미지는 10Mb이하로만 가능합니다."
            ]);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required',
            'content' => 'required',
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => json_decode($validator->errors()->__toString(), true)
            ]);
        }

        $subject = $request->input('subject');
        $content = $request->input('content');

        //이미지 파일 경로 확인.
        $imgs = \App::make('helper')->getEditorImgs($content);

        //디비 저장
        $payload = [
            'subject' => $subject,
            'content' => $content,
        ];
        $educatonInfo = new EducatonInfo($payload);
        $educatonInfo->save();

        //에디터의 파일에 대한 타입 아이디 부여
        if (count($imgs) > 0) {
            foreach ($imgs as $img) {
                $rs = EditorFile::whereNull('type_id')->where('type', '=', '1')->where('file_path', '=', $img)->get();
                if ($rs) {
                    foreach ($rs as $file) {
                        $file->type_id = $educatonInfo->id;
                        $file->save();
                    }
                }
            }
        }

        //썸네일 추가
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
                    $file_path = \App::make('helper')->putResizeS3(File::FILE_DIR, $file);
                }

                $payload = [
                    'type' => 1,
                    'type_id' => $educatonInfo->id,
                    'file_name' => $file_name,
                    'file_path' => $file_path,
                    'file_size' => $file->getSize(),
                    'file_mimetype' => $file->getMimeType(),
                    'vimeo_id' => $vimeo_id,
                ];
                $file = new File($payload);
                $file->save();
            }
        }

        BatchPush::dispatch(['type' => 'educatonInfo', 'type_id' => $educatonInfo->id, 'param' => []]);

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '등록 되었습니다.');
        $result = Arr::add($result, 'id', $educatonInfo->id);

        return response()->json($result);
    }

    public function destroy(Request $request, $id)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->mtype, ['a'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $row = EducatonInfo::find($id);
        if (empty($row)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '삭제할 게시물이 없습니다.');
            return response()->json($result);
        }

        $rs = EditorFile::where('type', '=', '1')->where('type_id', '=', $row->id)->get();
        if ($rs) {
            foreach ($rs as $file) {
                if ($file->vimeo_id) {
                    $vimeo = new VimeoController();
                    $rs = $vimeo->delete2($file->vimeo_id);
                } else {
                    $rs = \App::make('helper')->deleteImage($file->file_path);
                }
                $file->delete();
            }
        }

        $rs = File::where('type', '=', '1')->where('type_id', '=', $row->id)->get();
        if ($rs) {
            foreach ($rs as $file) {
                if ($file->vimeo_id) {
                    $vimeo = new VimeoController();
                    $rs = $vimeo->delete2($file->vimeo_id);
                } else {
                    $rs = \App::make('helper')->deleteImage($file->file_path);
                }
                $file->delete();
            }
        }

        $commonComments = CommonComment::where('type','=','1')->where('type_id','=',$id)->get();
        if ($commonComments->count() > 0) {
            foreach ($commonComments as $commonComment) {
                $commonComment->forceDelete();
            }
        }
        $commonHistories = CommonHistory::where('type','=','1')->where('type_id','=',$id)->get();
        if ($commonHistories->count() > 0) {
            foreach ($commonHistories as $commonHistory) {
                $commonHistory->forceDelete();
            }
        }

        $row->delete();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '삭제 되었습니다.');

        return response()->json($result);
    }

    public function update(Request $request, $id)
    {
        $result = array();
        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();

        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        if (!in_array($user->mtype, ['a'])) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '권한이 없습니다.');
            return response()->json($result);
        }

        $validator = Validator::make($request->all(), [
            'upload_files' => [new UploadFile],
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => "업로드 하려는 파일은 이미지만 가능하고 이미지는 10Mb이하로만 가능합니다."
            ]);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required',
            'content' => 'required',
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => json_decode($validator->errors()->__toString(), true)
            ]);
        }

        $row = EducatonInfo::find($id);
        if (empty($row)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '게시물이 없습니다.');
            return response()->json($result);
        }

        $subject = $request->input('subject');
        $content = $request->input('content');

        //이미지 파일 경로 확인.
        $imgs = \App::make('helper')->getEditorImgs($content);

        //기존 이미지 파일 경로 확인.
        $old_imgs = \App::make('helper')->getEditorImgs($row->content);

        //배열 차이
        $create_arr = array_diff($imgs,$old_imgs);
        $remove_arr = array_diff($old_imgs,$imgs);

        $row->subject = $subject;
        $row->content = $content;
        $row->update();

        //에디터의 파일에 대한 타입 아이디 부여
        if (count($create_arr) > 0) {
            foreach ($create_arr as $img) {
                $rs = EditorFile::whereNull('type_id')->where('type', '=', '1')->where('file_path', '=', $img)->get();
                if ($rs) {
                    foreach ($rs as $file) {
                        $file->type_id = $row->id;
                        $file->save();
                    }
                }
            }
        }

        //에디터 사용 안되는 에디터 이미지 삭제
        if (count($remove_arr) > 0) {
            foreach ($remove_arr as $img) {
                $file = EditorFile::where('type', '=', '1')->where('file_path', '=', $img)->first();
                if ($file) {
                    if ($file->vimeo_id) {
                        $vimeo = new VimeoController();
                        $rs = $vimeo->delete2($file->vimeo_id);
                    } else {
                        $rs = \App::make('helper')->deleteImage($file->file_path);
                    }
                    $file->delete();
                }
            }
        }

        //썸네일 변경
        $upload_files = $request->file('upload_files');
        if ($upload_files) {
            //기존 이미지 삭제 처리
            $file = File::where('type','=','1')
                ->where('type_id','=',$row->id)
                ->first();
            if ($file->vimeo_id) {
                $vimeo = new VimeoController();
                $rs = $vimeo->delete2($file->vimeo_id);
            } else {
                $rs = \App::make('helper')->deleteImage($file->file_path);
            }
            $file->delete();

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
                    $file_path = \App::make('helper')->putResizeS3(File::FILE_DIR, $file, 1160,180);
                }

                $payload = [
                    'type' => 1,
                    'type_id' => $row->id,
                    'file_name' => $file_name,
                    'file_path' => $file_path,
                    'file_size' => $file->getSize(),
                    'file_mimetype' => $file->getMimeType(),
                    'vimeo_id' => $vimeo_id,
                ];
                $file = new File($payload);
                $file->save();
            }
        }

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '수정 되었습니다.');
        $result = Arr::add($result, 'id', $row->id);

        return response()->json($result);
    }

    public function show(Request $request, $id)
    {
        $result = array();

        $user_id = $request->input('user');
        $user = RaonMember::whereIdx($user_id)->first();
        if (empty($user)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '사용자 정보가 없습니다.');
            return response()->json($result);
        }

        $row = EducatonInfo::find($id);
        if (empty($row)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 게시물이 없습니다.');
            return response()->json($result);
        }

        if ($user->mtype == 's') {
            if (CommonHistory::where('type','=','1')->where('type_id','=',$id)->where('sidx', $user->idx)->count() === 0) {
                $commonHistory = new CommonHistory([
                    'type' => '1',
                    'type_id' => $id,
                    'hidx' => $user->hidx,
                    'midx' => $user->midx,
                    'sidx' => $user->idx
                ]);
                $commonHistory->save();
            }
        } else {
            if ($user->mtype == 'm') {
                if (CommonHistory::where('type','=','1')->where('type_id','=',$id)->where('midx', $user->idx)->count() === 0) {
                    $commonHistory = new CommonHistory([
                        'type' => '1',
                        'type_id' => $id,
                        'hidx' => $user->hidx,
                        'midx' => $user->midx,
                    ]);
                    $commonHistory->save();
                }
            } else {
                if ($user->mtype == 'h') {
                    if (CommonHistory::where('type','=','1')->where('type_id','=',$id)->where('hidx', $user->idx)->count() === 0) {
                        $commonHistory = new CommonHistory([
                            'type' => '1',
                            'type_id' => $id,
                            'hidx' => $user->hidx,
                        ]);
                        $commonHistory->save();
                    }
                }
            }
        }

//        $content = $row->content;
        $content = \App::make('helper')->replaceEditorImgs($row->content);

        $file = File::where('type','=','1')
            ->where('type_id','=',$row->id)
            ->first();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'subject', $row->subject);
        $result = Arr::add($result, 'content', $content);
        $result = Arr::add($result, 'image', $file ? \App::make('helper')->getImage($file->file_path): null);
        $result = Arr::add($result, 'image_id', $file ? $file->id: null);
        $datetime = strtotime($row->created_at);
        $this_date = Carbon::create(date('Y', $datetime), date('m', $datetime), date('d', $datetime));
        $result = Arr::add($result, "date", date('Y.m.d H:i', $datetime)." ".\App::make('helper')->dayOfKo($this_date, 2));

        return response()->json($result);
    }

    public function index(Request $request)
    {
        $result = array();

        $validator = Validator::make($request->all(), [
            'limit' => ['min_digits:0'],
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => json_decode($validator->errors()->__toString(), true)
            ]);
        }

        $limit = $request->input('limit');

        $rs = EducatonInfo::orderByDesc('educaton_infos.created_at')
            ->select('educaton_infos.*', 'files.file_path')
            ->leftJoin('files', function ($q) {
                $q->on('educaton_infos.id', '=', 'files.type_id')->on('files.type',DB::raw(1));
            })
            ->when($limit, function ($q) use ($limit) {
                $q->limit($limit);
            })
            ->get();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            foreach ($rs as $index => $row) {
                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.subject", $row->subject);
                $result = Arr::add($result, "list.{$index}.image", $row->file_path ? \App::make('helper')->getImage($row->file_path): null);
                $datetime = strtotime($row->created_at);
                $this_date = Carbon::create(date('Y', $datetime), date('m', $datetime), date('d', $datetime));
                $result = Arr::add($result, "list.{$index}.date", date('Y.m.d', $datetime)." ".\App::make('helper')->dayOfKo($this_date, 2));
            }
        }

        return response()->json($result);
    }

    public function education()
    {
//        $rs = RaonMember::where(function($query) {
//            $query->where('mtype', 's')->where('s_status', 'Y');
//        })->orWhere(function($query) {
//            $query->where('mtype', 'm')->where('m_status', 'Y');
//        })->orWhere(function($query) {
//            $query->where('mtype', 'h')->where('h_status', 'Y');
//        })
//            ->pluck('idx')
//            ->toArray();
//
//        $arr_push = UserAppInfo::whereIn('user_id', $rs)
//            ->where('notice_alarm', 'Y')
//            ->whereNotNull('push_key')
//            ->take(10)
//            ->pluck('push_key')
//            ->toArray();
//
//        $arr_push = array_unique($arr_push);
//        $arr_push = array_values($arr_push);
//
//        $myId = UserAppInfo::where('user_id', '132895')->where('device_kind', 'iOS')->pluck('push_key')->toArray();
//
//        $arr_push = array_merge($myId, $arr_push);

//        $arr_push = array_unique($arr_push);
//        $arr_push = array_values($arr_push);

//        dd($arr_push);



        $req = Request::create('/api/educatonInfo/list', 'GET');

        $res = $this->index($req);
        $list = $res->original['list'] ?? [];
        // \App::make('helper')->vardump($list);

        return view('education/list', [
            'list' => $list,
        ]);
    }

    public function educationView($id)
    {
        $uesrId = \App::make('helper')->getUsertId();
        $userType = \App::make('helper')->getUsertType();
        $eventReq = Request::create('/api/educatonInfo/view/'.$id, 'GET', [
            'user' => $uesrId
        ]);
        $res = $this->show($eventReq, $id);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        return view('education/view',[
            'row' => $res->original ?? [],
            'id' => $id,
        ]);
    }

    public function educationWrite(Request $request, $id="")
    {
        $mode = "w";
        if ($id != "") {
            $mode = "u";
            $appNoticeReq = Request::create('/educatonInfo/view/'.$id, 'GET', [
                'user' => \App::make('helper')->getUsertId()
            ]);
            $res = $this->show($appNoticeReq, $id);

            if ($res->original['result'] != 'success') {
                $error = \App::make('helper')->getErrorMsg($res->original['error']);
                \App::make('helper')->alert($error);
            }

        }

        return view('education/write', [
            'mode' => $mode,
            'id' => $id,
            'row' => $res->original ?? [],
        ]);
    }

    public function educationWriteAction(Request $request)
    {
//        \App::make('helper')->vardump($request->all());
//        \App::make('helper')->vardump($request->allFiles());
//        exit;
        $mode = $request->input('mode') ?? '';
        $id = $request->input('id') ?? '';

        $request->merge([
            'user' => \App::make('helper')->getUsertId(),
        ]);
        if ($mode == 'u') {
            $res = $this->update($request, $id);
        } else {
            $res = $this->store($request);
        }
        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        $mode = $request->input('mode') ?? '';

        \App::make('helper')->alert( (($mode == 'u')?"수정":"등록")."되었습니다.", '/education/view/'.$res->original['id']);

    }

    public function educationDelete($id)
    {
        $req = Request::create('/educatonInfo/delete/'.$id, 'POST', [
            'user' => \App::make('helper')->getUsertId(),
        ]);
        $res = $this->destroy($req, $id);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        \App::make('helper')->alert("삭제되었습니다.", "/education");
    }

}
