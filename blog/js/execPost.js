/**
 * データをPOSTする
 * @param String アクション
 * @param Object POSTデータ連想配列
 * 記述元Webページ http://fujiiyuuki.blogspot.jp/2010/09/formjspost.html
 * サンプルコード
 * <a onclick="execPost('/hoge', {'fuga':'fuga_val', 'piyo':'piyo_val'});return false;" href="#">POST送信</a>
 */
function execPost(action, data, form_id="") {
    var input_file = document.getElementById("input_file");
    form = document.getElementById(form_id);
    var form_data = new FormData(form);

    if (data !== undefined) {
        for (var paramName in data) {
            form_data.append( paramName , data[paramName] );
        }
    }

    /*
    var file_list = input_file.files;
    if(file_list){
        var i;
        var num = file_list.length;
        for(i=0;i < num;i++){
            // File オブジェクトを取得する
            var file = file_list[i];

            // ファイル名を取得する
            var file_name = file.name;

            // 送信データを追加する
            form_data.append( file_name , file );
        }
    }*/

    var xhr = new XMLHttpRequest();
    xhr.onload = function (e){
        // レスポンスボディを取得する
        console.log(xhr.responseText );
    };
    xhr.open("POST" , action);
    xhr.send(form_data);
}
