function previewImage(obj, id, clear_flag, maxWidth='50px', maxHeight='50px')
{
    var count = function() {
        var a = 1;
        return function( cUp = false, init = false ) {
            if( init == true ) a = 1;
            else {
                if( cUp == true ) a++;
            }
            return a;
        };
    }();

    if( clear_flag ) {
        document.getElementById(id).innerHTML = '';
        count( true );
    }
    for (i = 0 ; i < obj.files.length ; i++) {
        var fileReader = new FileReader();
        fileReader.onload = (function (e) {
            /*
            const frame = document.createElement("div");
            const image = new Image();
            image.src = e.target.result;
            frame.append(image);
            document.getElementById(id).append(frame);
            */
            //tmp = '<form target="upload" method="POST" action="upload.php"></form>';
            tmp = '<label class="input_image_file">';
            tmp += '<img src="' + e.target.result + '" id="preview-image' + count(true) +'" class="preview-image" style="max-width:' + maxWidth + '; max-height:' + maxHeight + ';">';
            tmp += '<img src="./image/close.png" id="close' + count() + '" class="close">';
            tmp += '<input type="submit" style="display: none;"></input></label>';
            //tmp += '</form>';
            document.getElementById(id).innerHTML += tmp;

            var preview = document.getElementById( "preview-image" + count() );
            var close = document.getElementById( "close" + count() );
            preview.addEventListener("mouseover", function (event) {
                close.style.opacity = 0.8;
            }, false);
            preview.addEventListener("mouseout", function (event) {
                close.style.opacity = 0.3;
            }, false);
        });
        fileReader.readAsDataURL(obj.files[i]);
    }
}
