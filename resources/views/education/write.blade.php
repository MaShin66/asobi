@extends('layout.home')
@section('bodyAttr')
class="body"
@endsection
@section('contents')
<?php
$title = "교육정보 작성";
$hd_bg = "5";
$back_link = "/education";
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

        <form action="/education/writeAction" id="frm" name="frm" method="post" onsubmit="return frm_form_chk(this);" enctype="multipart/form-data" class="mt-3">
            <input type="hidden" name="mode" value="{{ $mode }}">
            <input type="hidden" name="id" value="{{ $id }}">
            <div class="form-group ip_wr mt-4 mt-lg-5 mb-0 mb-lg-4">
                <div class="ip_tit d-flex align-items-center">
                    <h5 class="mr-3">썸네일 이미지</h5>
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
            <div class="ip_wr mt-4">
                <div class="ip_tit d-flex align-items-center justify-content-between">
                    <h5>제목</h5>
                </div>
                <textarea class="form-control" style="height: 5rem" placeholder="제목을 입력해주세요" name="subject">{{ $row['subject'] ?? '' }}</textarea>
            </div>
            <div class="ip_wr mt-4">
                <!-- EDITOR -->
                <textarea class="form-control" name="content" placeholder="내용을 입력해주세요" rows="5">{!! $row['content'] ?? '' !!}</textarea>
                <script type="text/javascript">
                    <!--
                    CKEDITOR.replace('content', {
                        extraPlugins: 'uploadimage, image2',
                        height : '300px',
                        filebrowserImageUploadUrl : '/api/editor/fileWrite?type=1',
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
                <button type="button" class="btn btn-gray text-white" onclick="location.href='/education'">목록</button>
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

<script>
var delete_ids = [];
var upload_cont = 0;
var multiform_idx = [];
var multiform_delete_idx = [];

@if($row['image'] ?? '')
upload_cont = 1;
@endif

var fsubmit = false;
function frm_form_chk(f) {
    if (fsubmit) {
        return false;
    }
    fsubmit = true;
    $("#fsubmit").prop('disabled',true);

    let upfileCnt = 0;
    @if($row['image'] ?? '')
    upfileCnt++;
    if (delete_ids.length > 0) {
        upfileCnt--;
    }
    @endif

    if ($('.upload_files').length + upfileCnt == 0) {
        fsubmit = false;
        $("#fsubmit").prop('disabled',false);
        jalert("썸네일을 등록해주세요.");
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
            jalert("썸네일을 등록해주세요.");
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

    $('#loading').show();

    return true;
}
$(document).ready(function() {
    var i = 0;
    $(".addBtn").on('click', function(e) {
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
        up.remove();
        let imgId = $(this).data('imgid');
        if (imgId == '{{ $row['image_id'] ?? '' }}') delete_ids.push(imgId);
    });
});
</script>
@endsection
