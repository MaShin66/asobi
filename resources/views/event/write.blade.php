@extends('layout.home')
@section('bodyAttr')
class="body"
@endsection
@section('contents')
<?php
$title = "이벤트 작성";
$hd_bg = "6";
$back_link = "/event";
?>
@include('common.headm02')

@include('editor.ckeditor')

<article class="sub_pg">
    <div class="container pt-4 pt_lg_50">
        <div class="d-none d-lg-block">
            @include('common.tabs')
        </div>
        <h1 class="tit_h1 ff_lotte fw_500 d-none d-lg-block mb-4 mb-lg-5">
            <?=$title?>
            <img src="/img/ic_tit.png" class="tit_img">
        </h1>
        <form action="/event/writeAction" id="frm" name="frm" method="post" onsubmit="return frm_form_chk(this);" enctype="multipart/form-data" class="mt-3">
            <input type="hidden" name="mode" value="{{ $mode }}">
            <input type="hidden" name="id" value="{{ $id }}">
            <div class="ip_wr mb-4">
                <div class="ip_tit">
                    <h5>구분</h5>
                </div>
                <div class="checks_wr">
                    <div class="checks">
                        <label>
                            <input type="radio" name="status" value="1" {{ isset($row['status']) && $row['status'] == 1 ? 'checked' : 'checked' }}>
                            <span class="ic_box"></span>
                            <div class="chk_p">
                                <p>진행중</p>
                            </div>
                        </label>
                    </div>
                    <div class="checks">
                        <label>
                            <input type="radio" name="status" value="0" {{ isset($row['status']) && $row['status'] == 0 ? 'checked' : '' }}>
                            <span class="ic_box"></span>
                            <div class="chk_p">
                                <p>종료</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group ip_wr mt-4 mt-lg-5 mb-0 mb-lg-4">
                <div class="ip_tit d-flex align-items-center">
                    <h5 class="mr-3">썸네일 이미지1</h5>
                </div>
                <div class="input-group-prepend" id="imgUpload">
                    <div class="image-upload2 addBtn mr-3">
                        <label for="upload_file">
                            <div class="upload-icon2">
                                <button type="button" class="btn del"></button>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group mt-3 help_msg">
                - 1160 X 180 해상도에 대응되는 이미지입니다.<br />
                - 1160 X 180 해상도로 리사이즈 됩니다.
            </div>

            <div class="form-group ip_wr mt-4 mt-lg-5 mb-0 mb-lg-4">
                <div class="ip_tit d-flex align-items-center">
                    <h5 class="mr-3">썸네일 이미지2</h5>
                </div>
                <div class="input-group-prepend" id="imgUpload2">
                    <div class="image-upload2 addBtn2 mr-3">
                        <label for="upload_file2">
                            <div class="upload-icon2">
                                <button type="button" class="btn del2"></button>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group mt-3 help_msg">
                - 가로폭 768px ~ 991px에 대응되는 이미지입니다.<br />
                - 680 X 140 해상도에 최적화되어, 리사이즈 됩니다.
            </div>

            <div class="form-group ip_wr mt-4 mt-lg-5 mb-0 mb-lg-4">
                <div class="ip_tit d-flex align-items-center">
                    <h5 class="mr-3">썸네일 이미지3</h5>
                </div>
                <div class="input-group-prepend" id="imgUpload3">
                    <div class="image-upload2 addBtn3 mr-3">
                        <label for="upload_file3">
                            <div class="upload-icon2">
                                <button type="button" class="btn del3"></button>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group mt-3 help_msg">
                - 가로폭 768px이하에 대응되는 이미지입니다.<br />
                - 500 X 125 해상도에 최적화되어, 리사이즈 됩니다.
            </div>

            <div class="mt-4">
                <div class="ip_wr">
                    <div class="ip_tit d-flex align-items-center justify-content-between">
                        <h5>이벤트 기간</h5>
                    </div>
                </div>
                <div class="grid02_list_input">
                    <input type="date" name="start" value="{{ $row['start'] ?? '' }}" class="form-control text-dark_gray">
                    <span class="fs_16 text-dark_gray">~</span>
                    <input type="date" name="end" value="{{$row['end'] ?? ''}}" class="form-control text-dark_gray">
                </div>
            </div>
            <div class="ip_wr mt-4">
                <div class="ip_tit d-flex align-items-center justify-content-between">
                    <h5>제목</h5>
                </div>
                <textarea class="form-control" style="height: 5rem" placeholder="제목을 입력해주세요" name="subject">{{ $row['subject'] ?? '' }}</textarea>
            </div>
            <div class="ip_wr mt-4">
                <textarea class="form-control" name="content" placeholder="내용을 입력해주세요" rows="5">{!! $row['content'] ?? '' !!}</textarea>
                <script type="text/javascript">
                    <!--
                    CKEDITOR.replace('content', {
                        extraPlugins: 'uploadimage, image2',
                        height : '300px',
                        filebrowserImageUploadUrl : '/api/editor/fileWrite?type=2',
                        enterMode : CKEDITOR.ENTER_BR,
                        toolbarGroups : [
                            { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
                            { name: 'links', groups: [ 'links' ] },
                            { name: 'insert', groups: [ 'insert' ] },
                            { name: 'tools', groups: [ 'tools' ] },
                            { name: 'document', groups: [ 'document', 'doctools', 'mode' ] },
                            { name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing' ] },
                            { name: 'forms', groups: [ 'forms' ] },
                            { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                            { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi', 'paragraph' ] },
                            '/',
                            { name: 'styles', groups: [ 'styles' ] },
                            { name: 'colors', groups: [ 'colors' ] },
                            { name: 'others', groups: [ 'others' ] },
                            { name: 'about', groups: [ 'about' ] },
                        ],
                        removeButtons : 'Find,Replace,SelectAll,Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Save,NewPage,Preview,Print,Templates,ShowBlocks,Undo,Redo,PasteFromWord,PasteText,Anchor,Flash,Smiley,SpecialChar,PageBreak,Iframe,Subscript,Superscript,CopyFormatting,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,About,Styles,Font',
                    });
                    //-->
                </script>
            </div>

            <div class="cmt_wr note_btns pt-0 pt_lg_50 pb-0 pb-lg-4">
                <button type="submit" id="fsubmit" class="btn btn-primary">전송</button>
                <button type="button" class="btn btn-gray text-white" onclick="location.href='/event'">목록</button>
            </div>
        </form>
    </div>
</article>

<div class="loading_wrap" id="loading" style="display: none;">
    <div class="loading_text">
        <i class="loading_circle"></i>
        <span>로딩중</span>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script>
    var delete_ids = [];
    var upload_cont = 0;
    var multiform_idx = [];
    var multiform_delete_idx = [];

    @if($row['image'] ?? '')
        upload_cont = 1;
    @endif

    var delete_ids2 = [];
    var upload_cont2 = 0;
    var multiform_idx2 = [];
    var multiform_delete_idx2 = [];

    @if($row['image2'] ?? '')
        upload_cont2 = 1;
    @endif

    var delete_ids3 = [];
    var upload_cont3 = 0;
    var multiform_idx3 = [];
    var multiform_delete_idx3 = [];

    @if($row['image3'] ?? '')
        upload_cont3 = 1;
    @endif

    var fsubmit = false;
    function frm_form_chk(f) {
        if (fsubmit) {
            return false;
        }
        fsubmit = true;
        $("#fsubmit").prop('disabled',true);
        let upfileCnt = 0;
        let upfileCnt2 = 0;
        let upfileCnt3 = 0;
        @if($row['image'] ?? '')
            upfileCnt++;
        if (delete_ids.length > 0) {
            upfileCnt--;
        }
        @endif
        @if($row['image2'] ?? '')
            upfileCnt2++;
        if (delete_ids2.length > 0) {
            upfileCnt2--;
        }
        @endif
        @if($row['image3'] ?? '')
            upfileCnt3++;
        if (delete_ids3.length > 0) {
            upfileCnt3--;
        }
        @endif

        if ($('.upload_files').length + upfileCnt == 0) {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("썸네일 이미지1를 등록해주세요.");
            return false;
        } else if (upfileCnt == 0) {
            let uploadBool = true;
            for (let i=0;i<$('.upload_files').length;i++) {
                if ($('.upload_files').eq(i).val() != "") {
                    uploadBool = false;
                    break;
                }
            }
            if (uploadBool) {
                fsubmit = false;
                $("#fsubmit").prop('disabled',false);
                jalert("썸네일 이미지1를 등록해주세요.");
                return false;
            }
        }

        if ($('.upload_files2').length + upfileCnt2 == 0) {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("썸네일 이미지2를 등록해주세요.");
            return false;
        } else if (upfileCnt2 == 0) {
            let uploadBool2 = true;
            for (let i=0;i<$('.upload_files2').length;i++) {
                if ($('.upload_files2').eq(i).val() != "") {
                    uploadBool2 = false;
                    break;
                }
            }
            if (uploadBool2) {
                fsubmit = false;
                $("#fsubmit").prop('disabled',false);
                jalert("썸네일 이미지2를 등록해주세요.");
                return false;
            }
        }

        if ($('.upload_files3').length + upfileCnt3 == 0) {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("썸네일 이미지3를 등록해주세요.");
            return false;
        } else if (upfileCnt3 == 0) {
            let uploadBool3 = true;
            for (let i=0;i<$('.upload_files3').length;i++) {
                if ($('.upload_files3').eq(i).val() != "") {
                    uploadBool3 = false;
                    break;
                }
            }
            if (uploadBool3) {
                fsubmit = false;
                $("#fsubmit").prop('disabled',false);
                jalert("썸네일 이미지3를 등록해주세요.");
                return false;
            }
        }

        if (f.subject.value == "") {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("제목을 입력해주세요.");
            return false;
        }

        var oEditor = CKEDITOR.instances.content;
        if(oEditor.getData()=="") {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("내용을 입력해주세요.");
            oEditor.focus();
            return false;
        }

        if(f.start.value=="" || f.end.value=="" ) {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("이벤트기간을 선택해주세요.");
            return false;
        } else {
            const mstart = moment(f.start.value);
            const mend = moment(f.end.value);

            if (mend <= mstart) {
                jalert("이벤트 종료일은 이벤트 시작일보다 이후날자를 선택하세요.");
                return false;
            }
        }

        $('#loading').show();

        return true;
    }
    $(document).ready(function() {
        var i = 0;
        $(".addBtn").on('click', function(e) {
            // console.log('ycommon.getUploadCount()',ycommon.getUploadCount())
            // console.log('ycommon.getUploadCount(upload_cont-delete_ids.length)',ycommon.getUploadCount(upload_cont-delete_ids.length))
            if (ycommon.getUploadCount(upload_cont-delete_ids.length) >= 1) {
                jalert("썸네일은 1개까지만 등록 가능합니다.");
                return;
            }
            let addForm = '<div class="image-upload2 mr-3" data-id="'+i+'" id="image-upload-'+i+'">'+
                '<label id="label_upload_file_'+i+'" for="upload_file_'+i+'">' +
                '<div class="upload-icon2">' +
                '<button type="button" class="btn del"></button>' +
                '</div>' +
                '</label>' +
                '<input id="upload_file_'+i+'" name="upload_files['+i+']" class="upload_files" data-id="'+i+'" type="file" accept="image/*" />' +
                '</div>';
            $('#imgUpload').append(addForm);
            $('#label_upload_file_'+i).trigger('click');
            i++;
        });

        @if($row['image'] ?? '')
        //수정시 썸네일 이미지 처리
        let addForm = '<div class="image-upload2 on mr-3" data-id="'+i+'" id="image-upload-'+i+'">'+
            '<label id="label_upload_file_'+i+'" for="upload_file_'+i+'">' +
            '<div class="upload-icon2">' +
            '<button type="button" class="btn del" data-imgid="{{ $row['image_id'] ?? '' }}"></button>' +
            '<img src="{{ $row['image'] ?? '' }}" />' +
            '</div>' +
            '</label>' +
            '<input id="upload_file_'+i+'" name="upload_files['+i+']" class="upload_files" data-id="'+i+'" type="file" accept="image/*" />' +
            '</div>';
        $('#imgUpload').append(addForm);
        @endif

        $(document).on('change', '.upload_files', function(e) {
            const imageMaxSize = 10 * 1024 * 1024; // 10MB
            const videoMaxSize = 10 * 10 * 1024 * 1024; // 100MB

            for (var i = 0; i < this.files.length; i++) {

                console.log(this.files[i].type);

                if (this.files[i].type.startsWith('image/')) {
                    if (this.files[i].size > imageMaxSize) {
                        jalert('파일 크기가 너무 큽니다. 10MB 이하의 파일을 선택하세요.');
                        this.value = '';
                        return;
                    }
                } else if (this.files[i].type.startsWith('video/')) {
                    if (this.files[i].size > videoMaxSize) {
                        jalert('파일 크기가 너무 큽니다. 100MB 이하의 파일을 선택하세요.');
                        this.value = '';
                        return;
                    }
                }
            }

            let id = $(this).data('id');
            ycommon.previewImage(e, id);
        });

        $(document).on('click', '.image-upload2 .del', function (e){
            let up = $(this).parents('.image-upload2');
            let id = up.data('id');
            up.remove();
            let imgId = $(this).data('imgid');
            if (imgId == '{{ $row['image_id'] ?? '' }}') delete_ids.push(imgId);

            multiform_delete_idx.push(id);
            ycommon.sortMultiformDeleteIdxs(multiform_delete_idx);
        });

        var ii = 0;
        $(".addBtn2").on('click', function(e) {
            // console.log('ycommon.getUploadCount()',ycommon.getUploadCount())
            // console.log('ycommon.getUploadCount(upload_cont-delete_ids.length)',ycommon.getUploadCount(upload_cont-delete_ids.length))
            if (ycommon.getUploadCount2(upload_cont2-delete_ids2.length) >= 1) {
                jalert("썸네일은 1개까지만 등록 가능합니다.");
                return;
            }
            let addForm2 = '<div class="image-upload2 mr-3" data-id2="'+ii+'" id="image-upload2-'+ii+'">'+
                '<label id="label_upload_file2_'+ii+'" for="upload_file2_'+ii+'">' +
                '<div class="upload-icon2">' +
                '<button type="button" class="btn del2"></button>' +
                '</div>' +
                '</label>' +
                '<input id="upload_file2_'+ii+'" name="upload_files2['+ii+']" class="upload_files2" data-id2="'+ii+'" type="file" accept="image/*" />' +
                '</div>';
            $('#imgUpload2').append(addForm2)
            $('#label_upload_file2_'+ii).trigger('click');
            ii++;
        });

        @if($row['image2'] ?? '')
        //수정시 썸네일 이미지 처리
        let addForm2 = '<div class="image-upload2 on mr-3" data-id2="'+ii+'" id="image-upload2-'+i+'">'+
            '<label id="label_upload_file2_'+ii+'" for="upload_file2_'+ii+'">' +
            '<div class="upload-icon2">' +
            '<button type="button" class="btn del2" data-imgid2="{{ $row['image_id2'] ?? '' }}"></button>' +
            '<img src="{{ $row['image2'] ?? '' }}" />' +
            '</div>' +
            '</label>' +
            '<input id="upload_file2_'+ii+'" name="upload_files2['+ii+']" class="upload_files2" data-id2="'+ii+'" type="file" accept="image/*" />' +
            '</div>';
        $('#imgUpload2').append(addForm2)
        @endif

        $(document).on('change', '.upload_files2', function(e) {
            let id2 = $(this).data('id2');
            ycommon.previewImage2(e, id2);
        });

        $(document).on('click', '.image-upload2 .del2', function (e){
            let up = $(this).parents('.image-upload2');
            let id2 = up.data('id2');
            up.remove();
            let imgId2 = $(this).data('imgid2');
            if (imgId2 == '{{ $row['image_id2'] ?? '' }}') delete_ids2.push(imgId2);

            multiform_delete_idx2.push(id2);
            ycommon.sortMultiformDeleteIdxs(multiform_delete_idx2);
        });

        var iii = 0;
        $(".addBtn3").on('click', function(e) {
            // console.log('ycommon.getUploadCount()',ycommon.getUploadCount())
            // console.log('ycommon.getUploadCount(upload_cont-delete_ids.length)',ycommon.getUploadCount(upload_cont-delete_ids.length))
            if (ycommon.getUploadCount3(upload_cont3-delete_ids3.length) >= 1) {
                jalert("썸네일은 1개까지만 등록 가능합니다.");
                return;
            }
            let addForm3 = '<div class="image-upload2 mr-3" data-id3="'+iii+'" id="image-upload3-'+iii+'">'+
                '<label id="label_upload_file3_'+iii+'" for="upload_file3_'+iii+'">' +
                '<div class="upload-icon2">' +
                '<button type="button" class="btn del3"></button>' +
                '</div>' +
                '</label>' +
                '<input id="upload_file3_'+iii+'" name="upload_files3['+iii+']" class="upload_files3" data-id3="'+iii+'" type="file" accept="image/*" />' +
                '</div>';
            $('#imgUpload3').append(addForm3)
            $('#label_upload_file3_'+iii).trigger('click');
            iii++;
        });

        @if($row['image3'] ?? '')
        //수정시 썸네일 이미지 처리
        let addForm3 = '<div class="image-upload2 on mr-3" data-id3="'+iii+'" id="image-upload3-'+iii+'">'+
            '<label id="label_upload_file3_'+iii+'" for="upload_file3_'+iii+'">' +
            '<div class="upload-icon2">' +
            '<button type="button" class="btn del3" data-imgid3="{{ $row['image_id3'] ?? '' }}"></button>' +
            '<img src="{{ $row['image3'] ?? '' }}" />' +
            '</div>' +
            '</label>' +
            '<input id="upload_file3_'+iii+'" name="upload_files3['+iii+']" class="upload_files2" data-id3="'+iii+'" type="file" accept="image/*" />' +
            '</div>';
        $('#imgUpload3').append(addForm3)
        @endif

        $(document).on('change', '.upload_files3', function(e) {
            let id3 = $(this).data('id3');
            ycommon.previewImage3(e, id3);
        });

        $(document).on('click', '.image-upload2 .del3', function (e){
            let up = $(this).parents('.image-upload2');
            let id3 = up.data('id3');
            up.remove();
            let imgId3 = $(this).data('imgid3');
            if (imgId3 == '{{ $row['image_id3'] ?? '' }}') delete_ids3.push(imgId3);

            multiform_delete_idx3.push(id3);
            ycommon.sortMultiformDeleteIdxs(multiform_delete_idx3);
        });
    });
</script>
@endsection
