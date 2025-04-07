@extends('layout.home')
@section('bodyAttr')
class="body"
@endsection
@section('contents')
<?php
$n_menu = "2";
$title = "내정보";
$hd_bg = "8";
$back_link = "/";
?>
@include('common.headm08')

<article class="sub_pg">
    <div class="container container-372 pt-5 pt_lg_50">
        <div class="mb-5 rounded-lg bg-primary_bg p-3">
            <h3 class="tit_h3 p-3 fw_600 text-primary">{{ $account['login_id'] ?? '' }}</h3>
            <p class="fs_14 px-3 pb-3 line_h1_5">사용하시는 아이디와 비밀번호로 여러 기기 <br />(다른 스마트폰)에서도 로그인하여 사용할 수 있습니다.</p>
        </div>
        @if(session('auth')['user_type'] =='s' ?? '')
        <div class="d-none d-lg-block ip_wr">
            <div class="ip_tit">
                <h5>프로필 사진</h5>
            </div>
        </div>
        <form name="profileForm" id="profileForm" method="POST" action="/mypage/profileAction" enctype="multipart/form-data">
            <div class="d-flex align-items-center justify-content-between">
                <div class="pf_img mx-0">
                    <div class="rect rounded-circle">
                        <img src="{{ $account['user_picture'] ?? '/img/profile_default.png' }}" id="profile_img">
                    </div>
                    <label for="picture" class="btn_pf">
                        <button class="btn rounded-circle"><img src="/img/ic_photo.png"></button>
                        <input type="file" name="picture" id="picture" value="" accept="image/*">
                    </label>
                </div>
                <button type="submit" class="btn btn-sm btn-light mt-0" style="width:100px;">수정</button>
            </div>
        </form>
        @endif

        <?php if ($_SERVER['REMOTE_ADDR'] === '221.148.221.127') : ?>
            <input type="text" name="device_id" id="device_id" value="{{ session('auth')['device_id'] ?? '' }}" />
            <input type="text" name="push_key" id="push_key" value="{{ session('auth')['push_key'] ?? '' }}" />
        <?php endif; ?>

        <div class="ip_wr mt-5">
            <div class="ip_tit">
                <h5>이름</h5>
            </div>
            <input type="text" class="form-control" placeholder="이름을 입력해주세요." value="{{ $account['user_name'] ?? '' }}" readonly>
        </div>
        <div class="ip_wr mt-4">
            <div class="ip_tit">
                <h5>휴대폰번호</h5>
            </div>
            <input type="text" class="form-control" placeholder="휴대폰번호를 입력해주세요." value="{{ $account['phone'] ?? '' }}" readonly>
        </div>

        <div class="d-none d-lg-block">
            @include('mypage.newPw')
        </div>

        <div class="d-block d-lg-none">
            <!-- <div class="ip_wr mt-4 pb-3">
                <div class="ip_tit d-flex align-items-center justify-content-between">
                    <h5>비밀번호</h5>
                    <a href="/mypage/resetPw"><u>변경하기</u></a>
                </div>
                <input type="password" class="form-control" placeholder="비밀번호를 입력해주세요." value="1111">
            </div> -->
            <div class="mt-4 pb-3">
                <button type="button" class="btn btn-block btn-outline-light border bg-white text-light" onclick="location.href='/mypage/resetPw'">비밀번호 변경</button>
            </div>
{{--            @if(session('auth')['user_type'] =='s' ?? '')--}}
{{--            <div class="pb-3">--}}
{{--                <button type="button" class="btn btn-block btn-outline-light border bg-white text-light" onclick="location.href='/mypage/editInfo'">회원정보 수정</button>--}}
{{--            </div>--}}
{{--            @endif--}}
        </div>
    </div>
</article>

<div class="loading_wrap" id="loading" style="display: none">
    <div class="loading_text">
        <i class="loading_circle"></i>
        <span>로딩중</span>
    </div>
</div>

<script>
    // 프로필 이미지 업로드
    const fileInput = document.getElementById("picture");
    const pfImg = document.getElementById("profile_img");

    if(pfImg) {
        fileInput.addEventListener('change', function() {
            const file = fileInput.files[0];
            console.log(file);

            if(file) {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onloadend = () => {
                    pfImg.src = reader.result;
                };
            }
        });
    }

    function FileSizeDelete() {
        ycommon.clearData();
        if (typeof window.ReactNativeWebView !== 'undefined') {
            window.ReactNativeWebView.postMessage(
                JSON.stringify({targetFunc: "storage"})
            );
        }
    }

    // document.querySelectorAll('a').forEach(function(anchor) {
    //     anchor.addEventListener('click', function(event) {
    //         $('#loading').show();
    //     });
    // });
    //
    // document.querySelectorAll('[onclick*="location.href"]').forEach(function(element) {
    //     element.addEventListener('click', function(event) {
    //         $('#loading').show();
    //     });
    // });
    //
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(event) {
            $('#loading').show();
            // $('#loading2').show();
        });
    });

    document.querySelector('.back_button').addEventListener('click', function(event) {
        $('#loading').show();
    });

    // document.querySelectorAll('button').forEach((elem) => {
    //     elem.addEventListener('click', () => {
    //         $('#loading').show();
    //     })
    // })

</script>

@endsection
