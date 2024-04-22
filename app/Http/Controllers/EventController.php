<?php

namespace App\Http\Controllers;

use App\AppendFile;
use App\CommonHistory;
use App\File;
use App\Rules\UploadFile;
use App\Models\RaonMember;
use App\Event;
use App\CommonComment;
use App\EditorFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Validator;
use App\Jobs\BatchPush;

use App\Rules\BooleanNum;

class EventController extends Controller
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
            'upload_files2' => [new UploadFile],
            'upload_files3' => [new UploadFile],
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
            'status' => ['required', new BooleanNum],
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => json_decode($validator->errors()->__toString(), true)
            ]);
        }

        $start = $request->input('start');
        $end = $request->input('end');
        if (strtotime($end) < strtotime($start)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '이벤트 종료일이 시작일보다 먼저일 수는 없습니다.');
            return response()->json($result);
        }

        $subject = $request->input('subject');
        $content = $request->input('content');
        $status = $request->input('status');

        //이미지 파일 경로 확인.
        $imgs = \App::make('helper')->getEditorImgs($content);

        //디비 저장
        $payload = [
            'subject' => $subject,
            'content' => $content,
            'status' => $status,
            'start' => $start,
            'end' => $end,
        ];
        $event = new Event($payload);
        $event->save();

        //에디터의 파일에 대한 타입 아이디 부여
        if (count($imgs) > 0) {
            foreach ($imgs as $img) {
                $rs = EditorFile::whereNull('type_id')->where('type', '=', '2')->where('file_path', '=', $img)->get();
                if ($rs) {
                    foreach ($rs as $file) {
                        $file->type_id = $event->id;
                        $file->save();
                    }
                }
            }
        }

        //썸네일1 추가
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
                    $file_path = \App::make('helper')->putResizeS3(File::FILE_DIR, $file, 1160,180);
                }

                $payload = [
                    'type' => 2,
                    'type_id' => $event->id,
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

        //썸네일2 추가
        $upload_files2 = $request->file('upload_files2');
        if ($upload_files2) {
            $vimeo = new VimeoController();
            foreach ($upload_files2 as $file) {
                $file_name = $file->getClientOriginalName();
                $vimeo_id = null;

                if (Str::startsWith($file->getMimeType(), 'video')) {
                    $vimeo_id = $vimeo->upload_simple($file);
                }

                if ($vimeo_id) {
                    $file_path = AppendFile::getVimeoThumbnailUrl($vimeo_id);
                } else {
                    $file = \App::make('helper')->rotateImage($file);
                    $file_path = \App::make('helper')->putResizeS3(File::FILE_DIR, $file, 680,140);
                }

                $payload = [
                    'type' => 6,
                    'type_id' => $event->id,
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

        //썸네일3 추가
        $upload_files3 = $request->file('upload_files3');
        if ($upload_files3) {
            $vimeo = new VimeoController();
            foreach ($upload_files3 as $file) {
                $file_name = $file->getClientOriginalName();
                $vimeo_id = null;

                if (Str::startsWith($file->getMimeType(), 'video')) {
                    $vimeo_id = $vimeo->upload_simple($file);
                }

                if ($vimeo_id) {
                    $file_path = AppendFile::getVimeoThumbnailUrl($vimeo_id);
                } else {
                    $file = \App::make('helper')->rotateImage($file);
                    $file_path = \App::make('helper')->putResizeS3(File::FILE_DIR, $file, 500,125);
                }

                $payload = [
                    'type' => 7,
                    'type_id' => $event->id,
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

        BatchPush::dispatch(['type' => 'event', 'type_id' => $event->id, 'param' => []]);

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'error', '등록 되었습니다.');
        $result = Arr::add($result, 'id', $event->id);

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

        $row = Event::find($id);
        if (empty($row)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '삭제할 게시물이 없습니다.');
            return response()->json($result);
        }

        $rs = EditorFile::where('type', '=', '2')->where('type_id', '=', $row->id)->get();
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

        $rs = File::where('type', '=', '2')->where('type_id', '=', $row->id)->get();
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

        $rs = File::where('type', '=', '6')->where('type_id', '=', $row->id)->get();
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

        $rs = File::where('type', '=', '7')->where('type_id', '=', $row->id)->get();
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

        $commonComments = CommonComment::where('type','=','2')->where('type_id','=',$id)->get();
        if ($commonComments->count() > 0) {
            foreach ($commonComments as $commonComment) {
                $commonComment->forceDelete();
            }
        }
        $commonHistories = CommonHistory::where('type','=','2')->where('type_id','=',$id)->get();
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
            'upload_files2' => [new UploadFile],
            'upload_files3' => [new UploadFile],
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
            'status' => ['required', new BooleanNum],
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        if($validator->fails()){
            return response()->json([
                'result' => 'fail',
                'error' => json_decode($validator->errors()->__toString(), true)
            ]);
        }

        $start = $request->input('start');
        $end = $request->input('end');
        if (strtotime($end) < strtotime($start)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '이벤트 종료일이 시자일보다 먼저일 수는 없습니다.');
            return response()->json($result);
        }

        $row = Event::find($id);
        if (empty($row)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '게시물이 없습니다.');
            return response()->json($result);
        }

        $subject = $request->input('subject');
        $content = $request->input('content');
        $status = $request->input('status');

        //이미지 파일 경로 확인.
        $imgs = \App::make('helper')->getEditorImgs($content);

        //기존 이미지 파일 경로 확인.
        $old_imgs = \App::make('helper')->getEditorImgs($row->content);

        //배열 차이
        $create_arr = array_diff($imgs,$old_imgs);
        $remove_arr = array_diff($old_imgs,$imgs);

        $row->subject = $subject;
        $row->content = $content;
        $row->start = $start;
        $row->end = $end;
        $row->status = $status;
        $row->update();

        //에디터의 파일에 대한 타입 아이디 부여
        if (count($create_arr) > 0) {
            foreach ($create_arr as $img) {
                $rs = EditorFile::whereNull('type_id')->where('type', '=', '2')->where('file_path', '=', $img)->get();
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
                $file = EditorFile::where('type', '=', '2')->where('file_path', '=', $img)->first();
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

        //썸네일1 변경
        $upload_files = $request->file('upload_files');
        if ($upload_files) {
            //기존 이미지 삭제 처리
            $file = File::where('type','=','2')
                ->where('type_id','=',$row->id)
                ->first();
            if ($file) {
                if ($file->vimeo_id) {
                    $vimeo = new VimeoController();
                    $rs = $vimeo->delete2($file->vimeo_id);
                } else {
                    $rs = \App::make('helper')->deleteImage($file->file_path);
                }
                $file->delete();
            }

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
                    $file_path = \App::make('helper')->putResizeS3(File::FILE_DIR, $file, 1160,180, true);
                }

                $payload = [
                    'type' => 2,
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

        //썸네일2 변경
        $upload_files2 = $request->file('upload_files2');
        if ($upload_files2) {
            //기존 이미지 삭제 처리
            $file = File::where('type','=','6')
                ->where('type_id','=',$row->id)
                ->first();
            if ($file) {
                if ($file->vimeo_id) {
                    $vimeo = new VimeoController();
                    $rs = $vimeo->delete2($file->vimeo_id);
                } else {
                    $rs = \App::make('helper')->deleteImage($file->file_path);
                }
                $file->delete();
            }

            $vimeo = new VimeoController();
            foreach ($upload_files2 as $file) {
                $file_name = $file->getClientOriginalName();
                $vimeo_id = null;

                if (Str::startsWith($file->getMimeType(), 'video')) {
                    $vimeo_id = $vimeo->upload_simple($file);
                }

                if ($vimeo_id) {
                    $file_path = AppendFile::getVimeoThumbnailUrl($vimeo_id);
                } else {
                    $file = \App::make('helper')->rotateImage($file);
                    $file_path = \App::make('helper')->putResizeS3(File::FILE_DIR, $file, 680,140, true);
                }

                $payload = [
                    'type' => 6,
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

        //썸네일3 변경
        $upload_files3 = $request->file('upload_files3');
        if ($upload_files3) {
            //기존 이미지 삭제 처리
            $file = File::where('type','=','7')
                ->where('type_id','=',$row->id)
                ->first();
            if ($file) {
                if ($file->vimeo_id) {
                    $vimeo = new VimeoController();
                    $rs = $vimeo->delete2($file->vimeo_id);
                } else {
                    $rs = \App::make('helper')->deleteImage($file->file_path);
                }
                $file->delete();
            }

            $vimeo = new VimeoController();
            foreach ($upload_files3 as $file) {
                $file_name = $file->getClientOriginalName();
                $vimeo_id = null;

                if (Str::startsWith($file->getMimeType(), 'video')) {
                    $vimeo_id = $vimeo->upload_simple($file);
                }

                if ($vimeo_id) {
                    $file_path = AppendFile::getVimeoThumbnailUrl($vimeo_id);
                } else {
                    $file = \App::make('helper')->rotateImage($file);
                    $file_path = \App::make('helper')->putResizeS3(File::FILE_DIR, $file, 500,125, true);
                }

                $payload = [
                    'type' => 7,
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

        $row = Event::find($id);
        if (empty($row)) {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 게시물이 없습니다.');
            return response()->json($result);
        }

        if ($user->mtype == 's') {
            if (CommonHistory::where('type','=','2')->where('type_id','=',$id)->where('sidx', $user->idx)->count() === 0) {
                $commonHistory = new CommonHistory([
                    'type' => '2',
                    'type_id' => $id,
                    'hidx' => $user->hidx,
                    'midx' => $user->midx,
                    'sidx' => $user->idx
                ]);
                $commonHistory->save();
            }
        } else {
            if ($user->mtype == 'm') {
                if (CommonHistory::where('type','=','2')->where('type_id','=',$id)->where('midx', $user->idx)->count() === 0) {
                    $commonHistory = new CommonHistory([
                        'type' => '2',
                        'type_id' => $id,
                        'hidx' => $user->hidx,
                        'midx' => $user->midx,
                    ]);
                    $commonHistory->save();
                }
            } else {
                if ($user->mtype == 'h') {
                    if (CommonHistory::where('type','=','2')->where('type_id','=',$id)->where('hidx', $user->idx)->count() === 0) {
                        $commonHistory = new CommonHistory([
                            'type' => '2',
                            'type_id' => $id,
                            'hidx' => $user->hidx,
                        ]);
                        $commonHistory->save();
                    }
                }
            }
        }

        $file = File::where('type','=','2')
            ->where('type_id','=',$row->id)
            ->first();
        $file2 = File::where('type','=','6')
            ->where('type_id','=',$row->id)
            ->first();
        $file3 = File::where('type','=','7')
            ->where('type_id','=',$row->id)
            ->first();
//        $content = $row->content;
        $content = \App::make('helper')->replaceEditorImgs($row->content);

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'subject', $row->subject);
        $result = Arr::add($result, 'content', $content);
        $result = Arr::add($result, 'image', $file ? \App::make('helper')->getImage($file->file_path): null);
        $result = Arr::add($result, 'image_id', $file ? $file->id: null);
        $result = Arr::add($result, 'image2', $file2 ? \App::make('helper')->getImage($file2->file_path): null);
        $result = Arr::add($result, 'image_id2', $file2 ? $file2->id: null);
        $result = Arr::add($result, 'image3', $file3 ? \App::make('helper')->getImage($file3->file_path): null);
        $result = Arr::add($result, 'image_id3', $file3 ? $file3->id: null);
        $result = Arr::add($result, 'start', $row->start);
        $result = Arr::add($result, 'end', $row->end);
        $result = Arr::add($result, 'status', $row->status);
        $status_text = "진행중";
        if ($row->status == "0" || strtotime($row->end) < time()) $status_text = "종료";
        else if ($row->status == "1" && strtotime($row->start) > time()) $status_text = "대기";
        $result = Arr::add($result, 'status_text', $status_text);
        $result = Arr::add($result, "date_range", date('Y.m.d', strtotime($row->start))." ~ ".date('Y.m.d', strtotime($row->end)) );

        return response()->json($result);
    }

    public function mainBanner()
    {
        $result = array();

        $rs = Event::orderByDesc('events.start')
            ->orderByDesc('events.id')
            ->select(DB::raw('events.*, a.file_path, b.file_path as file_path2, c.file_path as file_path3'))
//            ->select('events.*', 'files.file_path')
            ->leftJoin('files as a', function ($q) {
                $q->on('events.id', '=', 'a.type_id')->on('a.type',DB::raw(2));
            })
            ->leftJoin('files as b', function ($q) {
                $q->on('events.id', '=', 'b.type_id')->on('b.type',DB::raw(6));
            })
            ->leftJoin('files as c', function ($q) {
                $q->on('events.id', '=', 'c.type_id')->on('c.type',DB::raw(7));
            })
            ->where('status', '1')
            ->where('start','<=',date('Y-m-d'))
            ->where('end','>',date('Y-m-d'))
            ->get();

        if ($rs) {
            $result = Arr::add($result, 'result', 'success');
            foreach ($rs as $index => $row) {
                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.subject", $row->subject);
                $result = Arr::add($result, "list.{$index}.image", $row->file_path ? \App::make('helper')->getImage($row->file_path): null);
                $result = Arr::add($result, "list.{$index}.image2", $row->file_path2 ? \App::make('helper')->getImage($row->file_path2): null);
                $result = Arr::add($result, "list.{$index}.image3", $row->file_path3 ? \App::make('helper')->getImage($row->file_path3): null);
                $status_text = "진행중";
                if ($row->status == "0" || strtotime($row->end) < time()) $status_text = "마감";
                else if ($row->status == "1" && strtotime($row->start) > time()) $status_text = "대기";
                $result = Arr::add($result, "list.{$index}.status_text", $status_text);
                $result = Arr::add($result, "list.{$index}.date_range", date('Y.m.d', strtotime($row->start))." ~ ".date('Y.m.d', strtotime($row->end)) );
            }
        } else {
            $result = Arr::add($result, 'result', 'fail');
            $result = Arr::add($result, 'error', '조회된 내역이 없습니디.');
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
        $status = $request->input('status');

        $rs = Event::orderByDesc(DB::raw('(select now() < events.end and events.status)'))
            ->orderByDesc('events.start')
            ->orderByDesc('events.id')
            ->select('events.*', 'files.file_path')
            ->leftJoin('files', function ($q) {
                $q->on('events.id', '=', 'files.type_id')->on('files.type',DB::raw(7));
            })
            ->when($user->mtype != 'a', function ($q) {
                $q->where('events.start', '<=', date('Y-m-d'));
                $q->where('events.end', '>', date('Y-m-d'));
                $q->where('events.status', '1');
            })
            ->when($limit, function ($q) use ($limit) {
                $q->limit($limit);
            })
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->get();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());

        if ($rs) {
            foreach ($rs as $index => $row) {
                $result = Arr::add($result, "list.{$index}.id", $row->id);
                $result = Arr::add($result, "list.{$index}.subject", $row->subject);
                $result = Arr::add($result, "list.{$index}.image", $row->file_path ? \App::make('helper')->getImage($row->file_path): null);

                $status_text = "진행중";
                if ($row->status == "0" || date('Y-m-d', strtotime($row->end)) < date('Y-m-d', time())) $status_text = "종료";
                else if ($row->status == "1" && strtotime($row->start) > time()) $status_text = "대기";
                $result = Arr::add($result, "list.{$index}.status_text", $status_text);
                $result = Arr::add($result, "list.{$index}.date_range", date('Y.m.d', strtotime($row->start))." ~ ".date('Y.m.d', strtotime($row->end)) );
            }
        }

        return response()->json($result);
    }

    public function event()
    {
        $uesrId = \App::make('helper')->getUsertId();
        $req = Request::create('/api/event/list', 'GET',[
            'user' => $uesrId
        ]);
        $res = $this->index($req);
        $list = $res->original['list'] ?? [];
//        \App::make('helper')->vardump($uesrId);
//         \App::make('helper')->vardump($list);

        return view('event/list', [
            'list' => $list,
        ]);
    }
    public function eventView($id)
    {
        $uesrId = \App::make('helper')->getUsertId();
        $userType = \App::make('helper')->getUsertType();
        $eventReq = Request::create('/api/event/view/'.$id, 'GET', [
            'user' => $uesrId
        ]);
        $res = $this->show($eventReq, $id);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        return view('event/view',[
            'row' => $res->original ?? [],
            'id' => $id,
        ]);
    }
    public function eventWrite(Request $request, $id="")
    {
        $mode = "w";
        if ($id != "") {
            $mode = "u";
            $appEventReq = Request::create('/event/view/'.$id, 'GET', [
                'user' => \App::make('helper')->getUsertId()
            ]);
            $res = $this->show($appEventReq, $id);

            if ($res->original['result'] != 'success') {
                $error = \App::make('helper')->getErrorMsg($res->original['error']);
                \App::make('helper')->alert($error);
            }
        }

        return view('event/write', [
            'mode' => $mode,
            'id' => $id,
            'row' => $res->original ?? [],
        ]);
    }

    public function eventWriteAction(Request $request)
    {
//        \App::make('helper')->vardump($request->allFiles());
//        exit;
        $mode = $request->input('mode') ?? '';
        $id = $request->input('id') ?? '';
        $status1 = $request->input('status') === '1' ?? false;

        $request->merge([
            'user' => \App::make('helper')->getUsertId(),
            'status' => $status1
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

        \App::make('helper')->alert( (($mode == 'u')?"수정":"등록")."되었습니다.", '/event/view/'.$res->original['id']);

    }

    public function eventDelete($id)
    {
        $req = Request::create('/event/delete/'.$id, 'POST', [
            'user' => \App::make('helper')->getUsertId(),
        ]);
        $res = $this->destroy($req, $id);

        if ($res->original['result'] != 'success') {
            $error = \App::make('helper')->getErrorMsg($res->original['error']);
            \App::make('helper')->alert($error);
        }

        \App::make('helper')->alert("삭제되었습니다.", "/event");
    }

    public function empty()
    {
        return view('empty2');
    }
}
