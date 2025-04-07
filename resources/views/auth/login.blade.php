@extends('layout.full')
@section('bodyAttr')
class="body"
@endsection
@section('contents')
<article class="sub_pg login_pg pt-0 pb-0">
  <div class="container">
    <div class="login_wr">
        <div class="logo_img text-center pb-5">
          <img src="/img/logo.svg" alt="아소비">
        </div>
        <form class="login-form" name="login-form" id="login-form" method="POST" action="/auth/loginAction">

            <?php if ($_SERVER['REMOTE_ADDR'] === '221.148.221.127') : ?>
                <input type="text" name="device_kind" id="device_kind" value="{{ session('auth')['device_kind'] ?? $device_kind }}" />
                <input type="text" name="device_type" id="device_type" value="{{ session('auth')['device_type'] ?? $device_type }}" />
                <input type="text" name="device_id" id="device_id" value="{{ session('auth')['device_id'] ?? $device_id }}" />
                <input type="text" name="push_key" id="push_key" value="{{ session('auth')['push_key'] ?? $push_key }}" />
            <?php else : ?>
                <input type="hidden" name="device_kind" id="device_kind" value="{{ session('auth')['device_kind'] ?? $device_kind }}" />
                <input type="hidden" name="device_type" id="device_type" value="{{ session('auth')['device_type'] ?? $device_type }}" />
                <input type="hidden" name="device_id" id="device_id" value="{{ session('auth')['device_id'] ?? $device_id }}" />
                <input type="hidden" name="push_key" id="push_key" value="{{ session('auth')['push_key'] ?? $push_key }}" />
            <?php endif; ?>

          <input type="text" name="login_id" id="login_id" class="form-control log_id mb-4" placeholder="아이디(전화번호)를 입력해주세요.">
          <input type="password" name="password" id="password" class="form-control log_pw" placeholder="비밀번호를 입력해주세요.">

          <div class="checks_wr pt-4 pb-3">
            <div class="checks checks-sm">
              <label>
                <input type="checkbox" name="auto_login" checked="checked" value="1">
                <span class="ic_box"></span>
                <div class="chk_p">
                  <p class="text-light">자동 로그인</p>
                </div>
              </label>
            </div>
          </div>
          <div class="pt-5">
            <button type="submit" class="btn btn-primary btn-lg btn-block">로그인</button>
{{--            <button type="button" class="d-none d-lg-block btn btn-blue btn-lg btn-block" onclick="location.href='/auth/join'">입회신청</button>--}}
          </div>
          <div class="pt-5 d-flex d-lg-none align-items-center justify-content-center">
            <p class="border-right"><a href="/auth/findId" class="text-light mx-3">아이디 찾기</a></p>
            <p class="border-right"><a href="/auth/findPw" class="text-light mx-3">비밀번호 찾기</a></p>
{{--            <p class=""><a href="/auth/join" class="text-light mx-3">입회신청</a></p>--}}
          </div>
        </form>
    </div>
    <div class="lg_btm">
      <button type="button" class="btn btn-block fs_13 fw_400 text-light h-100 rounded-0 border-right" onclick="modalShow('policy')">개인정보처리방침</button>
      <button type="button" class="btn btn-block fs_13 fw_400 text-light h-100 mt-0" onclick="modalShow('terms')">서비스 이용약관</button>
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
    // window.onpopstate = function(event) {
    //     // 페이지 이동(뒤로가기 등)이 감지되었을 때 실행되는 함수
    //     $('#loading').hide(); // 원하는 동작 수행
    // };

    // document.querySelectorAll('form').forEach(form => {
    //     form.addEventListener('submit', function(event) {
    //         $('#loading').show();
    //     });
    // });

    //window.onload = function () {
    //    alert("현재 접속량이 많아 일시적으로 느려질 수 있습니다.");
    //}
</script>

@endsection
