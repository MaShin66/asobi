@extends('layout.home')
@section('bodyAttr')
class="body sub_bg4"
@endsection
@section('contents')
<?php
$title = "출석부 관리";
$hd_bg = "4";
$back_link = "/";
?>
@include('common.headm02')

<article class="sub_pg sub_bg sub_bg4">
    <div class="container pt-4 pt_lg_50">
        <div class="d-none d-lg-block">
            @include('common.tabs')
        </div>

        <div class="mb-4 mb-lg-5">
            <div class="d-block d-lg-flex align-items-center justify-content-between">
                <h1 class="tit_h1 ff_lotte fw_500 pt-5 pt-lg-0 position-relative z_index_5">
                    <span class="d-none d-lg-inline-block"><?=$title?></span>
                    <span class="d-inline-block d-lg-none">날짜를 선택해 주세요.</span>
                    <img src="/img/ic_tit.png" class="tit_img">
                </h1>
                <form name="attendForm" id="attendForm" method="GET" action="/attend" class="col-lg-6 px-0">
                    <div class="m_top mb-0 mt-0 mt-lg-3 mt-lg-0 pt-3 pt-lg-0">
                        <div class="input-group justify-content-start justify-content-lg-end">
                            <input type="month" name="ym" id="ym" value="{{ $ym }}" class="form-control form-control-lg col-lg-6" onchange="form_ym_change()">
                            @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='m')
                            <!-- ※ 아래의 select, 교육원일 때만 노출 -->
                            <div class="position-relative gr_r">
                                <select name="day" id="dateSelect" class="form-control bg-white custom-select m_select" onchange="this.form.submit()">
                                    <option value="all" selected>전체</option>
                                </select>
                            </div>
                            @endif
                        </div>
                        <div class="m_top_ico d-block d-lg-none">
                            <img src="/img/m1_top.png">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($day == '' || $day == 'all')
            <!-- 전체선택시 -->
            @if(count($list) > 0)
            <ul class="grid03_list note_stu_list attend_list">
                @foreach($list as $l)
                <li>
                    <a href="javascript:;" onclick="calModalShow('attendView', {{ $l['id'] }})">
                        <div class="d-flex align-items-center">
                            <div class="rect rounded-circle">
                                <img src="{{ $l['profile_image'] ?? '/img/profile_default.png' }}">
                            </div>
                            <p class="fs_16 fw_700 ml-3">{{ $l['name'] }}</p>
                        </div>
                        <p class="fs_16 text-right">{{ $l['attendance_cont'] ?? 0 }} / {{ $l['attendance_all_cont'] ?? 0 }}일</p>
                    </a>
                </li>
                @endforeach
            </ul>
            @else
            <div class="nodata">
                <p>조회된 학생이 없습니다.</p>
            </div>
            @endif
            <!-- // 전체선택시 -->
        @else
            <!-- 날짜선택시 -->
            <!-- <form name="attendAction" id="attendAction" method="POST" action="/attend/attendAction"> -->
            <form name="attendAction" id="attendAction" onsubmit="return false;">
                <input type="hidden" name="ym" id="ym" value="{{ $ym }}">
                <input type="hidden" name="day" id="day" value="{{ $day }}">
                <input type="hidden" name="val" id="val" value="">
                @if(count($list) > 0)
                <ul class="grid03_list note_stu_list">
                    @foreach($list as $l)
                    <li>
                        <div class="d-flex align-items-center">
                            <div class="rect rounded-circle">
                                <img src="{{ $l['profile_image'] ?? '/img/profile_default.png' }}">
                            </div>
                            <p class="fs_16 fw_700 ml-3 line_text line1_text">{{ $l['name'] }}</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="d-flex align-items-center pr-2 pr-sm-3">
                                <p class="fs_14 mr-2">등원 </p>
                                <div class="toggle_wr">
                                    <input type="checkbox" value="in-{{ $l['id'] }}" name="in{{ $l['id'] }}" id="in{{ $l['id'] }}" @if($l['attendance_in'] && $l['attendance_in'] == 1) checked @endif onchange="inoutChange(this)">
                                    <label for="in{{ $l['id'] }}" class="toggle_switch">
                                        <span class="toggle_btn"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="d-flex align-items-center pl-2 pl-sm-3">
                                <p class="fs_14 mr-2">하원 </p>
                                <div class="toggle_wr">
                                    <input type="checkbox" value="out-{{ $l['id'] }}" name="out{{ $l['id'] }}" id="out{{ $l['id'] }}" @if($l['attendance_out'] && $l['attendance_out'] == 1) checked @endif onchange="inoutChange(this)">
                                    <label for="out{{ $l['id'] }}" class="toggle_switch">
                                        <span class="toggle_btn"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
                @else
                <div class="nodata">
                    <p>조회된 학생이 없습니다.</p>
                </div>
                @endif
            </form>
            <!-- // 날짜선택시 -->
        @endif

        <!-- <ul class="pagination">
            <li class=""><a href="#" class="page_btn prev"><img src="/img/ic_arrow_left_gr.png"></a></li>
            <li class=""><a href="#" class="on">1</a></li>
            <li class=""><a href="#">2</a></li>
            <li class=""><a href="#">3</a></li>
            <li class=""><a href="#">4</a></li>
            <li class=""><a href="#">5</a></li>
            <li class=""><a href="#" class="page_btn next"><img src="/img/ic_arrow_right_gr.png"></a></li>
        </ul> -->

        <div id="modalConts"></div>
    </div>
</article>
<!-- alert -->
<div class="alert_wrap alert9999 text-center">
    <p class="fs_14 fc_gr17" id="alertText"></p>
</div>
<!-- // alert -->

<script>
    function form_ym_change() {
        let f = $("#attendForm");
        f.find('select[name=day]').val("").prop("selected",true);
        // document.attendAction.submit();
        f.submit();
    }

    const dates = {!! $attendList !!};
    // 날짜 리스트
    function dateList() {
        const ymText = '{{ $ym }}';
        let year = month = lastDate = '';

        if(ymText != '') {
            const ymArr = ymText.split("-");
            year = ymArr[0] ?? '';
            month = ymArr[1] ?? '';
            lastDate = new Date(year, month, 0).getDate();

            let dateText = '';
            dateText += `<option value="all" selected>전체</option>`;
            // for (let i = 0; i < lastDate; i++) {
            //     dateText += `<option value="${i+1}">${i+1}일</option>`;
            // }
            dates.map((d) => {
                dateText += `<option value="${d}">${d}일</option>`;
            });
            $("#dateSelect").html(dateText);
        }
    }

    // 캘린더 모달 처음 데이터
    function modalData(id) {
        let action = `/attend/view/${id}?ajax=1&list=1`;
        let data = { ym: '{{ $ym }}' };

        ycommon.ajaxJson('get', action, data, undefined, function (data) {
            // console.log(data);
            $("#modalConts").html("").html(data.calendar);
            @if($list)
            $('#attendView .btn_back').removeAttr("onclick");
            $('#attendView .btn_back').on('click',function(){
                calModalHide('attendView');
            });
            @endif
        });
    }
    // 캘린더 모달
    function calModalShow(tgId, userId) {
        modalShow(tgId);
        modalData(userId);
    }
    function calModalHide(tgId) {
        modalHide(tgId);
        $("#modalConts").html("");
    }
    $(document).on("click", ".parents_attend .modal_bg", function(e) {
        const modalConts = $(e.target) || $(e.target).parents(".modal_wrap");
        if (!($(e.target).hasClass('modal_wrap') || $(e.target).parents(".modal_wrap").hasClass('modal_wrap'))) {
            calModalHide('attendView');
        }
    });

    // 등,하원 선택
    function inoutChange(_this) {
        // alert()
        const f = $("#attendAction");

        const userId = '{{ session('auth')['user_id'] ?? "" }}';
        const ym = f.find("input[name=ym]").val();
        const day = f.find("input[name=day]").val();
        const typeId = _this.value;
        f.find('input[name=val]').val(typeId);

        let year = month = type = id = attendIn = attendOut = check = "";
        if (ym != '') {
            const ymArr = ym.split('-');
            year = ymArr[0] ?? '';
            month = ymArr[1] ?? '';
        }
        if(typeId != '') {
            const tiArr = typeId.split('-');
            type = tiArr[0] ?? '';
            id = tiArr[1] ?? '';
            attendIn = f.find(`input[name=in${id}]`).is(':checked') ? 1 : 0;
            attendOut = f.find(`input[name=out${id}]`).is(':checked') ? 1 : 0;

            if(type == 'in') {
                check = attendIn;
            } else {
                if (attendIn == 0) {
                    f.find(`input[name=out${id}]`).prop("checked", false);
                    jalert('등원 후에 하원처리하실 수 있습니다.');
                    return false;
                }
                check = attendOut;
            }
        }

        let action = '/api/attendance/write';
        let data = {
            user: userId,
            student: id,
            year, month, day, type, check,
        };
        ycommon.ajaxJson('post', action, data, undefined, function(data) {
            if (data.result == 'success') {
                alertShow('9999', '출석처리가 변경되었습니다.');
            } else {
                jalert(data.error);
            }
        });
    }

    $(window).on("load", function() {
        dateList();
        @if($day !== '')
            $("#dateSelect").val({{ $day }}).prop("selected", true);
        @endif
    });
</script>

@endsection
