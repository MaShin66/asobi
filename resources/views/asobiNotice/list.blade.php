@extends('layout.home')
@section('bodyAttr')
class="body sub_bg3"
@endsection
@section('contents')
<?php
$title = "공지사항";
?>
@include('common.headm07')

<article class="sub_pg sub_bg sub_bg3">
    <div class="container pt-4 pt_lg_50">
        <div class="mb-4 mb-lg-5">
            <div class="d-block d-lg-flex align-items-center justify-content-between">
                <h1 class="d-none d-lg-block tit_h1 ff_lotte fw_500 cursor_pointer" onclick="document.location.href='/asobiNotice'">
                    <?=$title?>
                    <img src="/img/ic_tit.png" class="tit_img">
                </h1>
                <form name="notice_form" id="notice_form" class="notice_form" method="GET" action="/asobiNotice">
                    <div class="search_wrap m_top mb-0 d-flex mt-0 mt-lg-3 mt-lg-0 w-100">
                        <div class="ip_sch_wr mr-0 mr-lg-4 col-6 col-lg-4 px-0">
                            <input type="search" name="search_text" value="{{ $search_text }}" class="form-control form-control-lg ip_search">
                            <button type="submit" class="btn btn_sch btn_sch2"></button>
                        </div>
                        <div class="input-group">
                            <input type="month" name="ym" value="{{ $ym }}" class="form-control form-control-lg" onchange="this.form.submit()">
                            <div class="gr_r col-12 col-lg-6 px-0 d-none d-lg-block">
                                <select name="type" id="filter_select" class="form-control bg-white custom-select m_select" onchange="filterChange(this.value)">
                                    <option value="">전체</option>
                                    <option value="h">지사</option>
                                    <option value="a">본사</option>
                                </select>
                            </div>
                        </div>
                        <div class="m_top_ico d-block d-lg-none">
                            <img src="/img/m3_top.png">
                        </div>
                        @if(isset(session('auth')['user_type']) && (session('auth')['user_type'] =='h' || session('auth')['user_type'] =='a'))
                        <!-- ※ 작성하기 버튼은 지사, 본사일 때만 노출 -->
                        <button type="button" class="d-none d-lg-block btn btn-md btn-primary ml-4 px-5" onclick="location.href='/asobiNotice/write'">작성하기</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- ※ 지사, 본사 공지만 노출!! -->
        <div class="pb-5">
            @if(count($list) > 0)
            <ul class="note_list grid01_list">
                @foreach($list as $k => $l)
                <li>
                    <a href="/asobiNotice/view/{{ $l['id'] }}">
                        <p class="text-dark_gray fs_13 fw_300 mb-3"><span class="text-primary fw_500 mr-2">[{{ $l['type'] }}공지]</span> {{ $l['date2'] }}</p>
                        <h4 class="tit_h4 mb-3">{{ $l['title'] }}</h4>
                        <p class="line2_text line_h1_4">{{ $l['content'] }}</p>
                    </a>
                </li>
                @endforeach
            </ul>
            @else
            <div class="nodata">
                <p>조회된 공지사항이 없습니다.</p>
            </div>
            @endif
        </div>

        @if(isset(session('auth')['user_type']) && (session('auth')['user_type'] =='h' || session('auth')['user_type'] =='a'))
        <!-- 모바일 작성 버튼 -->
        <!-- ※ 작성하기 버튼은 지사, 본사일 때만 노출 -->
        <div class="f_btn_wr d-block d-lg-none">
            <button type="button" class="btn float_btn" onclick="location.href='/asobiNotice/write'"><img src="/img/ic_write.png" style="width: 3rem;"></button>
        </div>
        @endif

    </div>
</article>

<script>
    // 필터 선택
    function filterValueChange(val) {
        $("#filter_select").val(val);
        $(".filter_modal button").removeClass("active");
        if(val == "") return $('.filter_modal button').eq(0).addClass("active");
        $(`.filter_modal button[value=${val}]`).addClass("active");
    }
    function filterChange(val) {
        filterValueChange(val);
        document.notice_form.submit();
    }
    $(document).ready(function() {
        @if(isset($type) && $type != "")
            filterValueChange('{{ $type }}');
        @endif
    });
</script>

@endsection
