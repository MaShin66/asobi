@extends('layout.home')
@section('bodyAttr')
class="body"
@endsection
@section('contents')
<?php
$title = "아소비 교육정보";
$hd_bg = "5";
$back_link = "/education";
?>
@include('common.headm02')

<article class="sub_pg">
    <div class="container pt-4 pt_lg_50">
        <div class="d-none d-lg-block">
            @include('common.tabs')
        </div>

        <div class="pb-4 mb-3 mb-lg-2 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex flex-column">
                <p class="fs_14 fw_300 mb-3 text-light">{{ $row['date'] }}</p>
                <h4 class="tit_h4 line_h1">{!! nl2br($row['subject']) !!}</h4>
            </div>
            @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='a')
            <!-- ※ 수정, 삭제 버튼은 본사일 때만 노출 -->
            <div class="position-relative d-block d-lg-none">
                <button type="button" class="btn p-0 btn_more h-auto"><img src="/img/ic_more.png" style="width: 1.6rem;"></button>
                <ul class="more_cont">
                    <li><button class="btn" onclick="location.href='/education/write/{{ $id }}'">수정</button></li>
                    <li><button class="btn" onclick="jalert2('삭제하시겠습니까?','삭제하기',function(){location.href='/education/delete/{{ $id }}';})">삭제</button></li>
                </ul>
            </div>
            @endif
        </div>
        <div class="pt-2 pt-lg-4 px-0 px-lg-4">
            <div class="editor_wrap fs_15">{!! $row['content'] !!}</div>
        </div>
        <!-- ※ 수정, 삭제 버튼은 본사일 때만 노출 -->
        <div class="botton_btns d-none d-lg-flex pt_80 pb-4">
            @if(isset(session('auth')['user_type']) && session('auth')['user_type'] =='a')
            <button type="button" class="btn btn-primary" onclick="location.href='/education/write/{{ $id }}'">수정</button>
            <button type="button" class="btn btn-gray text-white" onclick="location.href='/education'">목록</button>
            <button type="button" class="btn btn-gray text-white" onclick="jalert2('삭제하시겠습니까?','삭제하기',function(){location.href='/education/delete/{{ $id }}';})">삭제</button>
            @else
            <button type="button" class="btn btn-gray text-white" onclick="location.href='/education'">목록</button>
            @endif
        </div>
{{--        <hr class="line mt-5 mb-3">--}}
{{--        <div class="pt-3">--}}
{{--            <div class="pb-0 pb-lg-4 px-0 px-lg-3 mx-0 mx-lg-3">--}}
{{--                <div class="d-flex align-items-center justify-content-between mb-4">--}}
{{--                    <div class="d-flex align-items-center">--}}
{{--                        <img src="/img/ic_comment.png" style="width: 2.5rem;">--}}
{{--                        <p class="text-primary fs_18 ml-2">댓글</p>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <form action="" class="cmt_wr">--}}
{{--                    <div class="cmt_wr_box">--}}
{{--                        <textarea name="" id="comment" cols="30" rows="10" placeholder="댓글을 입력해주세요." class="form-control"></textarea>--}}
{{--                        <button type="button" class="btn btn-sm btn-primary wr_btn" onclick="Comment()">등록</button>--}}
{{--                    </div>--}}
{{--                </form>--}}
{{--            </div>--}}
{{--            <div id="commetHtml"></div>--}}
{{--        </div>--}}
    </div>
</article>

<div class="loading_wrap" id="loading" style="display: none">
    <div class="loading_text">
        <i class="loading_circle"></i>
        <span>로딩중</span>
    </div>
</div>

<script>
    //댓글 리스트
    function CommentList() {
        let action = `/api/commonComment/list?user=${userId}&type=1&type_id={{$id}}`;
        let data = '';
        ycommon.ajaxJson('get', action, data, undefined, function (res) {
            let data = res.list;
            // console.log(res);
            // console.log(data);

            let commentListHtml = '';
            let replyListHtml = '';

            data?.map(e => {
                let commentHtml = '';
                if (!e.pid) {
                    commentHtml = `
                            <hr class="line2 mb-3" />
                            <div class="py-3">
                                <div class="d-flex align-items-center justify-content-between cmt_tit">
                                    <div class="d-flex align-items-center justify-content-start cmt_name">
                                        <div class="rect rounded-circle mr-3">
                                            <img src="${e.writer_picture !== null ? e.writer_picture : '/img/profile_default.png'}">
                                        </div>
                                        <div>
                                            <p class="line1_text fw_600 fs_15">${e.writer}</p>
                                            <p class="fs_14 fw_300 text-light pt-2 d-none d-lg-block">${e.date}</p>
                                        </div>
                                    </div>
                                    ${e.comment !== '댓글이 삭제되었습니다.' && e.writer_id == {{session('auth')['user_id']}} ? `
                                    <div class="position-relative">
                                        <button type="button" class="btn p-0 btn_more h-auto" onclick="btn_more(event, ${e.id})"><img src="/img/ic_more2.png" style="width: 1.6rem;"></button>
                                        <ul id="more_cont${e.id}" class="more_cont">
                                            <li><button class="btn" onclick="btn_update(${e.id})">수정</button></li>
                                            <li><button class="btn" onclick="btn_delete(${e.id})">삭제</button></li>
                                        </ul>
                                    </div>
                                    ` : ''}
                                </div>
                                <p class="fs_14 line_h1_4 py-3 text-break" id="comment_wr${e.id}">${e.comment}</p>
                                <div class="c_commentList d-flex align-items-center justify-content-between justify-content-lg-end pb-3">
                                    <p class="fs_14 fw_300 text-light d-block d-lg-none">${e.date}</p>
                                    ${e.comment !== '댓글이 삭제되었습니다.' ? `
                                    <button type="button" class="btn btn_reply" onclick="reCommentInput(${e.id}, '${e.writer}')"><img src="/img/ic_comment2.png" style="width: 1.4rem;" class="mr-2">답글</button>
                                    ` : ''}
                                </div>
                                <div id="update_wr${e.id}" class="recmt_wr update_wr"></div>
                                <div id="recmt_wr${e.id}" class="recmt_wr comment_wr"></div>
                                <div id="replyHtml${e.pid}"></div>
                            </div>
                        `;
                }else {
                    commentHtml = `<div class="bg-light_gray reply_wr mt-3 py-3">
                            <div class="d-flex align-items-start justify-content-start container-fluid pt-3">
                                <img src="/img/ic_reply.png" style="width: 2rem;">
                                <div class="w-100 ml-3">
                                    <div class="d-flex align-items-center justify-content-between cmt_tit">
                                        <div class="d-flex align-items-center justify-content-start cmt_name">
                                            <div class="rect rounded-circle mr-3">
                                                <img src="${e.writer_picture !== null ? e.writer_picture : '/img/profile_default.png'}">
                                            </div>
                                            <div>
                                                <p class="line1_text fw_600 fs_15">${e.writer}</p>
                                                <p class="fs_14 fw_300 text-light pt-2 d-none d-lg-block">${e.date}</p>
                                            </div>
                                        </div>
                                        ${e.comment !== '댓글이 삭제되었습니다.'  && e.writer_id == {{session('auth')['user_id']}} ? `
                                        <div class="position-relative">
                                            <button type="button" class="btn p-0 btn_more h-auto" onclick="btn_more(event, ${e.id})"><img src="/img/ic_more2.png" style="width: 1.6rem;"></button>
                                            <ul id="more_cont${e.id}" class="more_cont">
                                                <li><button class="btn" onclick="btn_update(${e.id})">수정</button></li>
                                                <li><button class="btn" onclick="btn_delete(${e.id})">삭제</button></li>
                                            </ul>
                                        </div>
                                        ` : ''}
                                    </div>
                                    <p class="fs_14 line_h1_4 py-3 text-break" id="comment_wr${e.id}">${e.comment}</p>
                                    <div class="pb-3 d-block d-lg-none">
                                        <p class="fs_14 fw_300 text-light">${e.date}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="update_wr${e.id}" class="recmt_wr update_wr mt-3"></div>`;

                    $(`#recmt_wr${e.pid}`).html(commentHtml);
                }
                commentListHtml += commentHtml;
            });
            $('#commetHtml').html(commentListHtml);
        });
    }

    //댓글 작성
    function Comment() {
        let comment = $('#comment').val();
        if(comment === ''){
            return false;
        }
        let action = `/api/commonComment/write`;
        let data = {user: userId ,type: 1, type_id: {{$id}}, comment: comment};
        ycommon.ajaxJson('post', action, data, undefined, function (res) {
            $('#comment').val('');
            CommentList();
        });
    }

    //대댓글 작성 폼 호출
    function reCommentInput(id,writer) {
        const inputHtml = `
                <div class="cmt_wr">
                    <div class="cmt_wr_info">
                        <p class="text-dark_gray"><span class="text-text2">${writer}</span>님에게 답글 쓰기</p>
                        <button type="button" class="btn" onclick="hideInputComment()"><img src="/img/ic_x.png"/></button>
                    </div>
                    <div class="cmt_wr_box">
                        <textarea name="" id="recomment" cols="30" rows="10" placeholder="답글을 입력해주세요." class="form-control"></textarea>
                        <button type="button" class="btn btn-sm btn-primary wr_btn" onclick="reComment(${id})">등록</button>
                    </div>
                </div>
            `;
        $('.comment_wr').html('');
        $('.update_wr').html('');
        $('#recmt_wr'+id).html(inputHtml);
    }

    //대댓글 작성
    function reComment(id) {
        let comment = $('#recomment').val();
        if(comment === ''){
            return false;
        }
        let action = `/api/commonComment/write`;
        let data = {user: userId ,type: 1, type_id: {{$id}}, comment: comment, pid: id};
        ycommon.ajaxJson('post', action, data, undefined, function (res) {
            CommentList();
        });
    }

    //더보기 버튼
    function btn_more(event,id) {
        event.stopPropagation();
        $('.more_cont').removeClass('on');
        $('.more_cont').slideUp();
        if ($(`#more_cont${id}`).hasClass('on')) {
            event.stopPropagation();
        } else {
            $(`#more_cont${id}`).removeClass('on');
            $(`#more_cont${id}`).slideUp();
        }
        $(`#more_cont${id}`).slideToggle();
        $(`#more_cont${id}`).addClass('on');
    }

    $('.more_cont .btn').on('click', function (e) {
        e.preventDefault();
    });

    //댓글 수정 버튼
    function btn_update (id){
        let comment = $(`#comment_wr${id}`).text();

        const inputHtml = `
                <div class="cmt_wr">
                    <div class="cmt_wr_info">
                        <p class="text-dark_gray">내 댓글 수정하기</p>
                        <button type="button" class="btn" onclick="hideInputComment()"><img src="/img/ic_x.png"/></button>
                    </div>
                    <div class="cmt_wr_box">
                        <textarea name="" id="update_comment${id}" cols="30" rows="10" placeholder="댓글을 입력해주세요." class="form-control">${comment}</textarea>
                        <button type="button" class="btn btn-sm btn-primary wr_btn" onclick="updateComment(${id})">수정</button>
                    </div>
                </div>
            `;
        $('.update_wr').html('');
        $('.comment_wr').html('');
        $('#update_wr'+id).html(inputHtml);
    }

    //댓글 수정
    function updateComment (id){
        let comment = $(`#update_comment${id}`).val();
        let action = `/api/commonComment/write/${id}`;
        let data = {user: userId , comment: comment};
        ycommon.ajaxJson('post', action, data, undefined, function (res) {
            // console.log(res);
            CommentList();
        });
    }
    //댓글 삭제
    function btn_delete (id){
        let action = `/api/commonComment/delete/${id}`;
        let data = {user: userId};
        ycommon.ajaxJson('post', action, data, undefined, function (res) {
            CommentList();
        });
    }
    function hideInputComment() {
        $('.update_wr').html('');
        $('.comment_wr').html('');
    }

    //초기 댓글 리스트 출력
    // $(window).on("load", function () {
    //     CommentList();
    // });

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

    document.querySelector('.back_button').addEventListener('click', function(event) {
        $('#loading').show();
    });
</script>
@endsection
