@extends('layout.home')
@section('bodyAttr')
    class="body"
@endsection
@section('contents')
    {{--    @if(isset(session('auth')['auto_login']) && isset(session('auth')['device_kind']) && session('auth')['auto_login'] =='1' && session('auth')['device_kind'] != 'web')--}}
    @if(isset(session('auth')['auto_login']) && session('auth')['auto_login'] =='1')
        <script>
            $(function () {
                window.webViewBridge.send('auto_login', {mt_id: "{{ session('auth')['user_id'] }}"}, function (res) {
                    console.log({{ session('auth')['user_id'] }});
                }, function (err) {
                    // console.error(err);
                });
                {{--console.log({{session('auth')['user_id']}});--}}
            });
        </script>
    @endif

    <?php
    $n_menu = '1';

    if (isset($_SESSION['error'])) {
        echo '<script>alert("' . $_SESSION['error'] . '")</script>';
        unset($_SESSION['error']);
    }
    ?>

    @include('common.headm01')
    <article class="idx_pg">
        <div class="container pt-5 pt_lg_50">
            <div class="d-block d-lg-flex idx_wrap">
                <div class="idx_left">
                    <!--
                        ※ PC, 모바일 타이틀 따로 잡혀있음 (현재 교육원 기준)
                        타이틀명 :
                        - 학부모 (모바일만)
                        - 교육원 : 아소비교육 공지사항
                        - 지사, 본사 : 교육원 공지사항
                    -->
                    <?php
//                    var_dump(session('auth'));
                    ?>
                    @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='s')
                        <!-- 학부모일 때 노출되는 부분 -->
                        <div class="mb-4 bg-light_gray rounded-xl position-relative">
                            <div class="idx_info_box d-flex align-items-center justify-content-start">
                                <div class="ch_img rect rounded-circle mr-4">
                                    @if(isset($main['picture']) && $main['picture'] !='')
                                        <img src="{{ $main['picture'] ?? '' }}" alt="프로필이미지">
                                    @else
                                        <img src="/img/profile_default.png" alt="프로필이미지">
                                    @endif
                                </div>
                                <div class="">
                                    <h2 class="tit_h2 ff_lotte fw_400 mb-2 line_text line1_text">{{ $main['user_name'] ?? '' }}</h2>
                                    <p class="fs_16 text-dark_gray line_text line1_text">{{ session('auth')['center_name'] ?? '' }}</p>
                                </div>
                            </div>
                            <div class="position-absolute idx_info_ico">
                                <img src="/img/ic_c_info2.png">
                            </div>
                        </div>
                    @else
                        <div class="idx_filter">
                            <div class="d-none d-lg-flex align-items-center justify-content-between">
                                <div class="sub_tit">
                                    <h1 class="tit_h1 fw_500 ff_lotte mb-n2">
                                        @if(isset(session('auth')['user_type']))
                                            @if(session('auth')['user_type'] =='m')
                                                아소비교육 공지사항
                                            @elseif(session('auth')['user_type'] =='h')
                                                교육원 공지사항
                                            @else
                                                지사/교육원 공지사항
                                            @endif
                                        @endif
                                    </h1>
                                    <i><img src="/img/ic_tit.png" alt="아이콘"/></i>
                                </div>
                                @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='m')
                                    <!-- ※ 교육원일 때만 아래의 더보기 버튼 노출 -->
                                    <a href="/asobiNotice" class="idx_btn">더보기</a>
                                @endif
                            </div>
                            <!--
                                지사일 때는 교육원 선택 select 만,
                                본사 일 때는 교육원 선택, 지사 선택 select 둘다 노출
                            -->
                            @if(isset(session('auth')['user_type']) && (session('auth')['user_type'] =='h' || session('auth')['user_type'] =='a'))
                                <div class="idx_notice_btns mt-0 mt-lg-4 mb-4 mb-lg-0">
                                    @if(session('auth')['user_type'] =='a')
                                        <div class="position-relative mr-3 m_select_wrap">
                                            <div class="input_wrap">
                                                <input type="text" id="searchText"
                                                       class="form-control custom-select m_select" autocomplete='off'
                                                       placeholder="지사 선택">
                                                <button class="m_delete"><img src="/img/ic_delete_sm.png"></button>
                                            </div>
                                            <ul id="searchList" class="m_select_list none_scroll_bar"></ul>
                                        </div>
                                    @endif
                                    <div class="position-relative m_select_wrap">
                                        <div class="input_wrap">
                                            <input type="text" id="searchText2"
                                                   class="form-control custom-select m_select" autocomplete='off'
                                                   placeholder="교육원 선택">
                                            <button class="m_delete"><img src="/img/ic_delete_sm.png"></button>
                                        </div>
                                        <ul id="searchList2" class="m_select_list none_scroll_bar"></ul>
                                    </div>
                                    <a href="/asobiNotice/write" class="d-none d-lg-block idx_btn2 ml-3">작성</a>
                                    <a href="/asobiNotice" class="d-none d-lg-block idx_btn ml-3">더보기</a>
                                </div>
                            @endif
                        </div>
                        <div class="d-flex d-lg-none align-items-center">
                            <a href="/asobiNotice" class="d-block rounded-lg overflow-hidden w-100">
                                <div class="d-flex align-items-center justify-content-between bg-light_gray p-4">
                                    <h4 class="tit_h4 fw_500 ff_lotte mb-n2">
                                        @if(isset(session('auth')['user_type']))
                                            @if(session('auth')['user_type'] =='m')
                                                아소비교육 공지사항
                                            @elseif(session('auth')['user_type'] =='h')
                                                교육원 공지사항
                                            @else
                                                지사/교육원 공지사항
                                            @endif
                                        @endif
                                    </h4>
                                    <img src="/img/ic_arrow_right_b.png" style="max-width: 2rem;">
                                </div>
                            </a>
                            @if(isset(session('auth')['user_type']) && (session('auth')['user_type'] =='a' || session('auth')['user_type'] =='h'))
                                <!-- ※ 본사, 지사일 때 아래의 작성 버튼 노출 -->
                                <button type="button" class="btn btn-primary btn_mw ml-3"
                                        onclick="location.href='/asobiNotice/write'">작성
                                </button>
                            @endif
                        </div>
                        <ul class="idx_notice mt-0 mt-lg-5 mb-4">
                            <li class="idx_notice_tit d-none d-lg-flex">
                                <p class="fs_16 fw_500 text-text2">NO</p>
                                <p class="fs_16 fw_500 text-text2">제목</p>
                                <p class="fs_16 fw_500 text-text2">작성일</p>
                            </li>
                            @if(count($appNotice) > 0)
                                @foreach($appNotice as $l)
                                    <li class="border-bottom">
                                        <a href="/asobiNotice/view/{{$l['id']}}" class="idx_notice_cont">
                                            <p class="d-none d-lg-block fs_13 fw_300 text-text2">{{$l['id']}}</p>
                                            <p class="fs_15 text-text2 line_text line1_text">{{$l['title']}}</p>
                                            <p class="fs_13 fw_300 text-dark_gray ml-2 ml-lg-5">{{$l['date3']}}</p>
                                        </a>
                                    </li>
                                @endforeach
                            @else
                                <li class="border-bottom idx_notice_cont_none">
                                    <p class="fs_15 text-text2">공지사항이 없습니다.</p>
                                </li>
                            @endif
                        </ul>
                    @endif
                </div>
                <div class="idx_right">
                    <div class="row m_menu_wrap">
                        <div class="col col-6 m_menu">
                            @if(isset($main['adviceNote']) && $main['adviceNote'] == 'Y')
                                <img src="/img/new_icon.png" class="new_icon">
                            @endif
                            @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='s')
                                <a href="/advice/list" class="">
                                    @else
                                        <a href="/advice" class="">
                                            @endif
                                            <div class="menu_name">
                                                <p class="fs_22 ff_lotte text-white">
                                                    알림장
                                                </p>
                                                <img src="/img/ic_arrow_right_w.png" style="max-width: 2rem;"
                                                     class="d-none d-lg-block">
                                            </div>
                                            <div class="menu_bg" style="">
                                                <img src="/img/m_menu_1.png">
                                            </div>
                                        </a>
                        </div>
                        <div class="col col-6 m_menu">
                            @if(isset($main['album']) && $main['album'] == 'Y')
                                <img src="/img/new_icon.png" class="new_icon">
                            @endif
                            <a href="/album" class="">
                                <div class="menu_name">
                                    <p class="fs_22 ff_lotte text-white">
                                        앨범
                                    </p>
                                    <img src="/img/ic_arrow_right_w.png" style="max-width: 2rem;"
                                         class="d-none d-lg-block">
                                </div>
                                <div class="menu_bg" style="">
                                    <img src="/img/m_menu_2.png">
                                </div>
                            </a>
                        </div>
                        <div class="col col-6 m_menu">
                            @if(isset($main['notice']) && $main['notice'] == 'Y')
                                <img src="/img/new_icon.png" class="new_icon">
                            @endif
                            <a href="/notice" class="">
                                <div class="menu_name">
                                    <!--
                                        ※ 학부모일 때, 공지사항
                                        나머지는, 학부모 공지
                                    -->
                                    <p class="fs_22 ff_lotte text-white">
                                        @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='s')
                                            공지사항
                                        @else
                                            회원 공지
                                        @endif
                                    </p>
                                    <img src="/img/ic_arrow_right_w.png" style="max-width: 2rem;"
                                         class="d-none d-lg-block">
                                </div>
                                <div class="menu_bg" style="">
                                    <img src="/img/m_menu_3_1.png" class="d-none d-lg-block">
                                    <img src="/img/m_menu_3.png" class="d-block d-lg-none">
                                </div>
                            </a>
                        </div>
                        <div class="col col-6 m_menu">
                            <!--
                                ※ 학부모일 때, /attend/view
                                나머지는, /attend
                            -->
                            @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='s')
                                <a href="/attend/view/{{ $userId }}">
                                    @else
                                        <a href="/attend">
                                            @endif
                                            <div class="menu_name">
                                                <p class="fs_22 ff_lotte text-white">출석부</p>
                                                <img src="/img/ic_arrow_right_w.png" style="max-width: 2rem;"
                                                     class="d-none d-lg-block">
                                            </div>
                                            <div class="menu_bg" style="">
                                                <img src="/img/m_menu_4.png">
                                            </div>
                                        </a>
                        </div>
                        <div class="col col-6 m_menu">
                            @if(isset($main['educatonInfo']) && $main['educatonInfo'] == 'Y')
                                <img src="/img/new_icon.png" class="new_icon">
                            @endif
                            <a href="/education" class="">
                                <div class="menu_name">
                                    <p class="fs_22 ff_lotte text-white">
                                        교육정보
                                    </p>
                                    <img src="/img/ic_arrow_right_w.png" style="max-width: 2rem;"
                                         class="d-none d-lg-block">
                                </div>
                                <div class="menu_bg" style="">
                                    <img src="/img/m_menu_5_1.png" class="d-none d-lg-block">
                                    <img src="/img/m_menu_5.png" class="d-block d-lg-none">
                                </div>
                            </a>
                        </div>
                        <div class="col col-6 m_menu">
                            @if(isset($main['event']) && $main['event'] == 'Y')
                                <img src="/img/new_icon.png" class="new_icon">
                            @endif
                            <a href="/event" class="pageMove">
                                <div class="menu_name">
                                    <p class="fs_22 ff_lotte text-white">
                                        이벤트
                                    </p>
                                    <img src="/img/ic_arrow_right_w.png" style="max-width: 2rem;"
                                         class="d-none d-lg-block">
                                </div>
                                <div class="menu_bg" style="">
                                    <img src="/img/m_menu_6.png">
                                </div>
                            </a>
                        </div>
                        @if(isset(session('auth')['user_type']) && session('auth')['user_type'] !=='s')
                            <!-- 학부모일 떄는 상담일지 메뉴 노출되지 않음! -->
                            <div class="col col-12 m_menu">
                                <a href="/counsel" class="">
                                    <div class="menu_name type02">
                                        <p class="fs_20 ff_lotte text-white">상담일지</p>
                                        <img src="/img/ic_arrow_right_w.png" style="max-width: 2rem;"
                                             class="d-block d-lg-none">
                                    </div>
                                    <div class="menu_bg d-none d-lg-block">
                                        <img src="/img/m_menu_7.png">
                                    </div>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @if(isset($mainBanner) && count($mainBanner) > 0)
                <div class="mt-4 mt-lg-5 rounded-lg overflow-hidden">
                    <div class="banner_slider">
                        <div class="swiper-wrapper">
                            @foreach($mainBanner as $l)
                                <div class="swiper-slide">
                                    <div class="d-none d-lg-block banner_img cursor_pointer" style="background-image:url('{{ $l['image'] ?? '' }}')" onclick="document.location.href='/event/view/{{ $l['id'] ?? '' }}'"></div>
                                    <div class="d-none d-md-block d-lg-none banner_img cursor_pointer" style="background-image:url('{{ $l['image2'] ?? '' }}')" onclick="document.location.href='/event/view/{{ $l['id'] ?? '' }}'"></div>
                                    <div class="d-block d-md-none banner_img cursor_pointer" style="background-image:url('{{ $l['image3'] ?? '' }}')" onclick="document.location.href='/event/view/{{ $l['id'] ?? '' }}'"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
        </div>

        <div class="loading_wrap" id="loading" style="display: none">
            <div class="loading_text">
                <i class="loading_circle"></i>
                <span>로딩중</span>
            </div>
        </div>

    </article>

    <script>
        function hClick(e) {
            let h = $(e.target).data('idx');
            let f = $("#selectAction");
            f.find('input[name=type]').val('branch');
            f.find('input[name=value]').val(h);
            f.submit();
        }

        function mClick(e) {
            let m = $(e.target).data('idx');
            let f = $("#selectAction");
            f.find('input[name=type]').val('center');
            f.find('input[name=value]').val(m);
            f.submit();
        }

        $(window).on("load", function () {
            @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='a')
            // 지사 선택
            let action = '/api/branch';
            let data = {user: '1'};
            ycommon.ajaxJson('get', action, data, undefined, function (data) {
                let autoSearchData = [];
                if (data.result !== undefined && data.result == 'success' && data.count > 0) {
                    for (let i = 0; i < data.list.length; i++) {
                        autoSearchData.push({idx: data.list[i].id, name: data.list[i].name});
                    }
                    autoSearch(autoSearchData, "searchList", "searchText", hClick, '{{ $branch }}');
                }
            });
            @endif

            @if($branch != "")
            // 교육원 선택
            let action2 = '/api/center';
            let data2 = {user: '{{ $branch }}'};
            ycommon.ajaxJson('get', action2, data2, undefined, function (data) {
                let autoSearchData = [];
                if (data.result !== undefined && data.result == 'success' && data.count > 0) {
                    for (let i = 0; i < data.list.length; i++) {
                        autoSearchData.push({idx: data.list[i].id, name: data.list[i].name});
                    }
                    autoSearch(autoSearchData, "searchList2", "searchText2", mClick, '{{ $center }}');
                } else {
                    autoSearch([], "searchList2", "searchText2", mClick);
                }
            });
            @endif
        });

        document.querySelectorAll('a').forEach(function(anchor) {
            anchor.addEventListener('click', function(event) {
                $('#loading').show();
            });
        });

        document.querySelectorAll('[onclick*="location.href"]').forEach(function(element) {
            element.addEventListener('click', function(event) {
                // $('#loading').show();
            });
        });

        // window.onload = function () {
        //    alert("현재 접속량이 많아 일시적으로 느려질 수 있습니다.");
        // }
    </script>
    <form name="selectAction" id="selectAction" method="POST" action="/main/selectAction">
        <input type="hidden" name="type" value=""/>
        <input type="hidden" name="value" value=""/>
    </form>
@endsection
