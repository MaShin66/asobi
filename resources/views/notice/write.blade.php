@extends('layout.home')
@section('bodyAttr')
class="body"
@endsection
@section('contents')
<?php
ini_set('memory_limit', '-1');
$title = "회원 공지 작성";
$hd_bg = "3";

$userAgent = $_SERVER['HTTP_USER_AGENT'];

$phpisIOS = false;
if (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false || strpos($userAgent, 'iPod') !== false) {
    $phpisIOS = true;
} else {
    $phpisIOS = false;
}
?>
@include('common.headm04')
@include('notice.preview')

@include('editor.ckeditor')

<article class="sub_pg">
    <div class="container pt-4 pt_lg_50">
        <div class="d-none d-lg-block">
            @include('common.tabs')
        </div>
        <div class="d-none d-lg-flex align-items-center justify-content-between mb-4 mb-lg-5">
            <h1 class="tit_h1 ff_lotte fw_500">
                <?=$title?>
                <img src="/img/ic_tit.png" class="tit_img">
            </h1>
            <div class="d-flex">
                @if($mode == 'w')
                <button type="button" class="btn btn-md border border-primary text-primary px-5 mr-3" onclick="jalert2('임시저장을 하시겠습니까?\n이미지와 동영상은 별도의 스토리지에 저장되며, 이미 저장된 이미지가 있을 수 있습니다.', '임시저장', tmpSave);">임시저장</button>
                @endif
                <button type="button" class="btn btn-md border border-primary text-primary px-5" onclick="getNoticePreview()">미리보기</button>
            </div>
        </div>

        <!--
            ※ 입력폼
            교육원일 때 : 제목, 작성일자, 사진, 내용 노출
            본사일 때 : 구분, 제목, 작성일자, 내용 노출
        -->
        <form name="noticeForm" id="noticeForm" class="mt-3" method="POST" action="/notice/writeAction" onsubmit="return frm_form_chk(this);" enctype="multipart/form-data">
            <input type="hidden" name="mode" value="{{ $mode }}">
            <input type="hidden" name="id" value="{{ $id }}">
            <input type="hidden" name="delete_ids" value="" />
            <input type="hidden" name="tmp_file_ids" value="" />

            <div class="grid02_list">
                <div class="ip_wr">
                    <div class="ip_tit d-flex align-items-center justify-content-between">
                        <h5>제목</h5>
                        <button type="button" class="btn p-0 h-auto" onclick="boardCopy('title')"><img src="/img/ic_copy.png" style="width: 2rem;"></button>
                    </div>
                    <input type="text" name="title" id="title" value="{{ $row['title'] ?? '' }}" class="form-control" placeholder="제목을 입력해주세요.">
                </div>
                <div class="ip_wr">
                    <div class="ip_tit d-flex align-items-center justify-content-between">
                        <h5>작성일자</h5>
                    </div>
                    <input type="date" name="date" id="ymd" class="form-control text-dark_gray" value="{{ $ymd }}" max="<?php echo date("Y-m-d") ?>" {{ isset($row['date']) && $row['date'] != '' ? 'disabled' : '' }}>
                </div>
            </div>

            <div class="form-group ip_wr mt-4 mt-lg-5 mb-0 mb-lg-4">
                <div class="ip_tit d-flex align-items-center">
                    <h5 class="mr-3">사진·동영상</h5>
                    <p class="fs_13 text-light"><span id="uploadCount">0</span>/20</p>
                </div>
                <div class="scroll_wrap none_scroll_bar_lg">
                    <div class="input-group-prepend" id="imgUpload">

                        <div class="image-upload2 addBtn mr-3" >
                            <label for="upload_file">
                                <div class="upload-icon2">
                                    <button type="button" class="btn del"></button>
                                </div>
                            </label>
                        </div>

                    </div>
                </div>
            </div>

            <div class="ip_wr mt-4">
                <div class="ip_tit d-flex align-items-center justify-content-between">
                    <h5>내용</h5>
                    <button type="button" class="btn p-0 h-auto" onclick="editorCopy();"><img src="/img/ic_copy.png" style="width: 2rem;"></button>
                </div>
                <textarea name="content" id="content" class="form-control" placeholder="내용을 입력해주세요" rows="5">{!! $row['content'] ?? '' !!}</textarea>
                <script type="text/javascript">
                    <!--
                    CKEDITOR.replace('content', {
                        extraPlugins: 'uploadimage, image2',
                        height : '300px',
                        // filebrowserImageUploadUrl : '/api/editor/fileWrite?type=1',
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
                        removeButtons : 'Image,Find,Replace,SelectAll,Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Save,NewPage,Preview,Print,Templates,ShowBlocks,Undo,Redo,PasteFromWord,PasteText,Anchor,Flash,Smiley,SpecialChar,PageBreak,Iframe,Subscript,Superscript,CopyFormatting,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,About,Styles,Font',
                    });
                    //-->
                </script>
            </div>

            <div class="cmt_wr note_btns pt-0 pt_lg_50 pb-0 pb-lg-4">
                <button type="submit" id="fsubmit" class="btn btn-primary">전송</button>
                <button type="button" class="d-none d-lg-block btn btn-gray text-white" onclick="location.href='/notice'">목록</button>
                @if($mode == 'w')
                <button type="button" class="d-block d-lg-none btn btn-gray text-white" onclick="jalert2('임시저장을 하시겠습니까?\n이미지와 동영상은 별도의 스토리지에 저장되며, 이미 저장된 이미지가 있을 수 있습니다.', '임시저장', tmpSave);">임시저장</button>
                @endif
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
    var tmp_file_ids = [];
    var tmp_file_delete_ids = [];
    var upload_cont = 0;
    var multiform_idx = [];
    var multiform_delete_idx = [];
    let isSetTmp = false;

    @if(isset($row['file']) && is_array($row['file']) && count($row['file']) > 0)
        upload_cont = {{ count($row['file']) }}
    @endif

    // alert(upload_cont)
    var fsubmit = false;

    function frm_form_chk(f) {
        if (fsubmit) {
            return false;
        }
        fsubmit = true;
        $("#fsubmit").prop('disabled',true);
        const currentDate = new Date();
        const ymdValue = new Date(f.ymd.value);

        // ycommon.setDeleteUploadFile(multiform_delete_idx);
        // console.log('multiform_delete_idx', multiform_delete_idx);
        //
        // for(let i=0; i < $('.upload_files').length; i++) {
        //     if ($('.upload_files').eq(i).val()) {
        //         for(let j=0; j<$('.upload_files')[i].files.length; j++) {
        //             console.log(i,j)
        //         }
        //     }
        // }
        // return false;

        if (delete_ids.length > 0) {
            f.delete_ids.value = delete_ids.join(',');
        }

        if (tmp_file_ids.length > 0) {
            f.tmp_file_ids.value = tmp_file_ids.join(',');
        }

        if (f.title.value == "") {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("제목을 입력해주세요.");
            return false;
        }

        if (f.ymd.value == "") {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("작성일자를 입력해주세요.");
            return false;
        }

        //if (ymdValue > currentDate) {
        //    fsubmit = false;
        //    $("#fsubmit").prop('disabled',false);
        //    jalert('미래 날짜는 선택할 수 없습니다.');
        //    return false;
        //}

        let contents = CKEDITOR.instances.content.getData();

        // if (f.content.value == "") {
        if (contents == "") {
            fsubmit = false;
            $("#fsubmit").prop('disabled',false);
            jalert("내용을 입력해주세요.");
            return false;
        }

        ycommon.setDeleteUploadFile(multiform_delete_idx);

        ycommon.deleteData('notice');
        ycommon.deleteData('file');

        $('#loading').show();

        return true;
    }

    $('.btn_preview').on('click',function(){
        // modalShow('notePreview');
        getNoticePreview();
    });

    function editorCopy() {
        let contents = CKEDITOR.instances.content.getData();
        try {
            var tempElem = document.createElement('textarea');
            tempElem.value = contents;
            document.body.appendChild(tempElem);

            tempElem.select();
            // textarea.setSelectionRange(0, 9999);
            var returnValue = document.execCommand("copy");
            document.body.removeChild(tempElem);
            console.debug(returnValue);
            if (!returnValue) {
                throw new Error('copied nothing');
            }
            alert('복사 되었습니다.');
        } catch (e) {
            alert("이 브라우저는 지원하지 않습니다.");
        }
    }

    function tmpSave() {
        let multiform_delete_idx2 = ycommon.getMultiformDeleteIdxs(multiform_delete_idx);
        if ($('.upload_files').length > 0 || tmp_file_delete_ids.length > 0) {
            const formData = new FormData();
            formData.append("user", userId);
            formData.append("type", '5');
            formData.append("isSetTmp", isSetTmp);
            if ($('.upload_files').length > 0) {
                for(let i=0; i < $('.upload_files').length; i++) {
                    let del_keys = [];
                    for(let j=0; j< multiform_delete_idx2.length; j++) {
                        let ds = multiform_delete_idx2[j].split('_');
                        if (ds[0] == i) del_keys.push(ds[1]);
                    }
                    if ($('.upload_files').eq(i).val()) {
                        for(let j=0; j<$('.upload_files')[i].files.length; j++) {
                            // console.log(del_keys, j,del_keys.indexOf(j+""))
                            if (del_keys.indexOf(j+"") === -1) formData.append("upload_files[]", $('.upload_files')[i].files[j]);
                        }
                    }
                }
            }
            if (tmp_file_delete_ids.length > 0) {
                formData.append("delete_files", tmp_file_delete_ids.join(','));
            }

            $('#loading').show();

            let action = `/api/tmpFileSave`;
            ycommon.ajaxJson('post', action, formData, undefined, function () {
                let title = $('#title').val();
                let ymd = $('#ymd').val();
                // let content = $('#content').val();
                let content = CKEDITOR.instances.content.getData();
                ycommon.setData('notice',{
                    title: title,
                    content: content,
                    ymd: ymd,
                });

                $('#loading').hide();
                jalert("임시저장 되었습니다.");
            },undefined,function (jqXHR, textStatus, errorThrown){
                $('#loading').hide();
                jalert("파일 임시저장에 실패하였습니다.");
            }, 30000,undefined,{processData:false, contentType: false});

            ycommon.setData('file', {
                file: 'Y'
            });
        } else {
            ycommon.deleteData('file');

            let title = $('#title').val();
            let ymd = $('#ymd').val();
            // let content = $('#content').val();
            let content = CKEDITOR.instances.content.getData();
            ycommon.setData('notice',{
                title: title,
                content: content,
                ymd: ymd,
            });
            jalert("임시저장 되었습니다.");
        }

    }

    function setTmpSave() {
        ycommon.setData('file', {
            file: 'Y'
        });

        isSetTmp = true;

        // console.log("임시 저장 불러오기!!!");
        let tmpData = ycommon.getData('notice');
        if (tmpData.title !== undefined) $('#title').val(tmpData.title);
        // if (tmpData.content !== undefined) $('#content').val(tmpData.content);
        if (tmpData.content !== undefined) {
            CKEDITOR.instances.content.setData(tmpData.content);
        }
        if (tmpData.ymd !== undefined) $('#ymd').val(tmpData.ymd);

        let fileData = ycommon.getData('file');

        if (fileData) {
            let action = `/api/tmpFiles`;
            ycommon.ajaxJson('get', action, {user: userId, type: "5"}, undefined, function (data) {
                // console.log(data)
                if (data.count !== undefined && data.count > 0) {
                    let privewUploade = '<div class="image-upload2 on mr-3 videoThumnail" data-id="{i}" id="image-upload-{i}">' +
                        '<label id="label_upload_file_{i}" for="upload_file_{i}">' +
                        '<div class="upload-icon2">' +
                        '<button type="button" class="btn del" data-tmpid="{image_id}"></button>' +
                        '{image}' +
                        '</div>' +
                        '</label>' +
                        '</div>';
                    let previewHtml = '<div class="att_img mb-4" id="imageVideo{i}">' +
                        '<div class="rounded overflow-hidden">' +
                        '{imageVideo}' +
                        '</div>' +
                        '</div>';

                    let privewUploadeTmp, previewHtmlTmp;

                    for (let i = 0; i < data.list.length; i++) {
                        tmp_file_ids.push(data.list[i].file_id);

                        privewUploadeTmp = privewUploade;
                        privewUploadeTmp = privewUploadeTmp.replaceAll("{i}", data.list[i].file_id);
                        privewUploadeTmp = privewUploadeTmp.replaceAll('{image_id}', data.list[i].file_id);
                        if (data.list[i].vimeo_id == "video") {
                            privewUploadeTmp = privewUploadeTmp.replaceAll('{image}', "<video><source src='" + data.list[i].file_path+'#t=1'+"' /></video>");
                        } else {
                            privewUploadeTmp = privewUploadeTmp.replaceAll('{image}', "<img src='" + data.list[i].file_path + "' />");
                        }

                        previewHtmlTmp = previewHtml;
                        previewHtmlTmp = previewHtmlTmp.replaceAll("{i}", data.list[i].file_id);
                        if (data.list[i].vimeo_id == "video") {
                            previewHtmlTmp = previewHtmlTmp.replaceAll('{imageVideo}', '<video><source src="' + data.list[i].file_path +"#t=1"+'" class="w-100"></video>');
                        } else {
                            previewHtmlTmp = previewHtmlTmp.replaceAll('{imageVideo}', '<img src="' + data.list[i].file_path + '" class="w-100">');
                        }

                        $('#imgUpload').append(privewUploadeTmp)
                        $('#imageVideo').append(previewHtmlTmp)

                        const attImgTag = document.querySelector('.att_img video');
                        if (attImgTag) {
                            attImgTag.load();
                            attImgTag.pause();
                        }

                        const videoThumnailTag = document.querySelectorAll('.videoThumnail');

                        videoThumnailTag.forEach((elem) => {
                            let videoTag = elem.querySelector('video');

                            if (videoTag) {
                                videoTag.load();
                                videoTag.pause();
                            }
                        })
                    }
                    setTimeout(function () {
                        ycommon.setUploadCount(tmp_file_ids.length);
                    }, 100);
                }
            });
        }
    }

    function getNoticePreview() {
        let title = $('#title').val();
        // let content = $('#content').val();
        let content = CKEDITOR.instances.content.getData();
        let ymd = $('#ymd').val();
        let ymdText = (ymd != "") ? ymd.replaceAll("-",".") + " " + ycommon.getYmdLable(ymd) : "";
        let d = new Date(),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear(),
            H = '' + d.getHours(),
            i = '' + d.getMinutes();
        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;
        if (H.length < 2) H = '0' + H;
        if (i.length < 2) i = '0' + i;
        let user_type = '';
        @if(isset(session('auth')['user_type']))
            @if(session('auth')['user_type'] =='m')
                user_type = '교육원';
            @elseif(session('auth')['user_type'] =='a')
                user_type = '본사';
            @endif
        @endif

        $('#typeModal').text(user_type);
        $('#ymdModal').text(ymdText);
        $('#titleModal').text(title);
        // $('#contentModal').text(content);
        $('#contentModal').html(content);
        $('#imageVideo').html();
        modalShow('noticePreview');
    }

    $(document).ready(function() {
        $("#chkStudentAll").on("click", function() {
            if($("#chkStudentAll").is(":checked")) $("input.chkStudent").prop("checked", true);
            else $("input.chkStudent").prop("checked", false);
        });

        var i = 0;
        $(".addBtn").on('click', function(e) {
            if (ycommon.getUploadCount(upload_cont-delete_ids.length+tmp_file_ids.length) >= 10) {
                jalert("사진 동영상은 10개까지만 등록 가능합니다.");
                return;
            }
            let addForm = '<div class="image-upload2 mr-3" data-id="'+i+'" id="image-upload-'+i+'">'+
                '<label id="label_upload_file_'+i+'" for="upload_file_'+i+'">' +
                '<div class="upload-icon2">' +
                '<button type="button" class="btn del"></button>' +
                '</div>' +
                '</label>' +
                '</div>';

            
            addForm += '<input id="upload_file_'+i+'" multiple="multiple" name="upload_files[]" class="upload_files d-none" data-id="'+i+'" type="file" accept="image/*,video/*" />';

            $('#imgUpload').append(addForm);
            $('#label_upload_file_'+i).trigger('click');
            i++;
        });

        @if(isset($row['file']) && is_array($row['file']) && count($row['file']) > 0)
        //수정시 썸네일 이미지 처리
        let privewUploade = '<div class="image-upload2 on mr-3" data-id="{i}" id="image-upload-{i}">'+
            '<label id="label_upload_file_{i}" for="upload_file_{i}">' +
            '<div class="upload-icon2">' +
            '<button type="button" class="btn del" data-imgid="{image_id}"></button>' +
            '<img src="{image}" />' +
            '</div>' +
            '</label>' +
            '</div>';
        let previewHtml = '<div class="att_img mb-4" id="imageVideo{i}">' +
            '<div class="rounded overflow-hidden">' +
            '<img src="{image}" class="w-100">' +
            '</div>' +
            '</div>' ;
        let privewUploadeTmp, previewHtmlTmp;
        @foreach($row['file'] as $file)
            privewUploadeTmp = privewUploade;
        privewUploadeTmp = privewUploadeTmp.replaceAll("{i}",'{{$file['file_id']}}');
        privewUploadeTmp = privewUploadeTmp.replaceAll('{image_id}','{{$file['file_id']}}');
        privewUploadeTmp = privewUploadeTmp.replaceAll('{image}','{{$file['file_path']}}');

        previewHtmlTmp = previewHtml;
        previewHtmlTmp = previewHtmlTmp.replaceAll("{i}",'{{$file['file_id']}}');
        previewHtmlTmp = previewHtmlTmp.replaceAll('{image}','{{$file['file_path']}}');

        $('#imgUpload').append(privewUploadeTmp)
        $('#imageVideo').append(previewHtmlTmp)
        @endforeach
        setTimeout(function (){
            ycommon.setUploadCount(upload_cont);
        },100);
        @endif

        $(document).on('change', '.upload_files', function(e) {
            const imageMaxSize = 10 * 1024 * 1024; // 10MB
            const videoMaxSize = 10 * 10 * 1024 * 1024 * 1.1; // 110MB

            let breaker = false;
            let videoCount = 0;
            for (var i = 0; i < this.files.length; i++) {
                if (this.files[i].type.startsWith('image/')) {
                    if (this.files[i].size > imageMaxSize) {
                            jalert('파일 크기가 너무 큽니다. 10MB 이하의 파일을 선택하세요.');
                            this.value = '';
                            return;
                    }
                } else if (this.files[i].type.startsWith('video/')) {
                    videoCount++;
                    document.querySelectorAll('video').forEach((elem) => {
                        if (elem) {
                            breaker = true;
                        }
                    })

                    if (breaker) {
                        jalert('동영상은 하나만 첨부할 수 있습니다.');
                        this.value = '';
                        return;
                    }

                    if (this.files[i].size > videoMaxSize) {
                        jalert('파일 크기가 너무 큽니다. 100MB 이하의 파일을 선택하세요.');
                        this.value = '';
                        return;
                    }
                }
            }

            if (videoCount > 1) {
                jalert('동영상은 하나만 첨부할 수 있습니다.');
                this.value = '';
                return;
            }

            $('#loading').show();

            let id = $(this).data('id');
            ycommon.previewImage(e, id, upload_cont-delete_ids.length+tmp_file_ids.length);
        });

        $(document).on('click', '.image-upload2 .del', function (e){
            let up = $(this).parents('.image-upload2');
            let id = up.data('id');
            up.remove();
            $('#imageVideo'+id).remove();

            let imgId = $(this).data('imgid');
            if (imgId !== undefined) delete_ids.push(imgId);

            let tmpId = $(this).data('tmpid');
            if (tmpId !== undefined) {
                tmp_file_ids = tmp_file_ids.filter((e) => e !== tmpId)
                tmp_file_delete_ids.push(tmpId);
            }

            multiform_delete_idx.push(id);
            ycommon.sortMultiformDeleteIdxs(multiform_delete_idx);

            setTimeout(function (){
                ycommon.setUploadCount(upload_cont-delete_ids.length+tmp_file_ids.length);
            },100);
        });

        @if($mode == 'w')
        let tmpData = ycommon.getData('notice');
        // 임시 저장 내용 있을 때 alert 띄워주기
        if (tmpData != null) {
            jalert2('임시 저장된 내용을 불러오시겠습니까?', '불러오기', setTmpSave);
        }
        @endif
    });
</script>

@endsection
