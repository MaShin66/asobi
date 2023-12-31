<?php

namespace App\Http\Controllers;

//use App\Board;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BoardController extends Controller
{
    public function faq(Request $request)
    {
        $search_text = $request->input('search_text') ?? '';
        $search_text = trim($search_text);

        $result = [];
//        $rs = Board::where('board_id', 7)->orderBy('id', 'desc')->get();
        $rso = DB::table('board_data as a')
            ->select('a.title', 'a.content', 'b.name')
            ->leftJoin('board_categories as b', 'a.category_id', '=', 'b.id')
            ->where('a.board_id', 7)
            ->orderBy('a.id', 'desc');

        if ($search_text) {
            $rso->where(function($q) use ($search_text) {
                $q
                ->where('a.title','like','%'.$search_text.'%')
                ->orWhere('a.content','like','%'.$search_text.'%');
            });
            $rso->where('title','like','%'.$search_text.'%');
        }

        $rs = $rso->get();

        $result = Arr::add($result, 'result', 'success');
        $result = Arr::add($result, 'count', $rs->count());
        if ($rs) {
            foreach ($rs as $index => $row) {
                $result = Arr::add($result, "list.{$index}.title", "[{$row->name}]{$row->title}");
                $result = Arr::add($result, "list.{$index}.content", $row->content);
            }
        }else{
//      $result = Arr::add($result, 'result', 'fail');
//        $result = Arr::add($result, 'error', '등록된 내용이 없습니다.');
        }
        return response()->json($result);
    }
}
