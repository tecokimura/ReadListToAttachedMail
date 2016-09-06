<?php
/**
 *  ファイルのパスから中身を読み込んで形式を確認し、配列で返す
 *  (ファイルが存在する 、csv,tsv 形式 、行頭に特定の文字を含まない)
 *  @author Tomari
 */

//動作部分
main( $argc, $argv );
exit;

/**
 * プログラムのメイン処理
 * @param $argc  コマンドラインからの引数の数 
 * @param $argv  コマンドラインからの引数の文字列
 */
function main( $argc, $argv ){
    $isViewHelp= false;
    $confFileName = getRunOption( $argv );
    
    if( empty( $confFileName ) ) {
        // ファイルのパスが渡されてないとき
        $isViewHelp = true;
        
    }else {
        
        //ファイルのパスが渡されたとき
        $confData = readConfigFile( $confFileName );
        
        var_dump( $confData );
    }
    
    // ヘルプの出力が必要な場合
    if ( $isViewHelp ) {
        echo "引数が間違っています。";
    }
}


/**
 *  ファイルのパスから中身を読み込んで配列にする
 *  1行目は個人ファイルが入っているディレクトリへのパスのため分ける
 *  ファイルの存在の有無の確認はここでは行わない。
 *  @author Tomari
 *  @param  string $path ファイルへのパス
 *  @return array $result [memberDirPath][aryMember][aryNotFormat][$aryPassMember]
 */
function readConfigFile( $path ){
    $result = array();
    $aryMember = array();
    $aryNotFormat = array();
    $aryPassMember = array();
    
    $aryFileText = file( $path, FILE_IGNORE_NEW_LINES );
    $memberDirPath = array_shift( $aryFileText );

    //2行目から先のテキストが正しいフォーマットか確認する
    foreach( $aryFileText as $text ){
        if( checkFormatCsvTsv( $text ) == true ){
            //csv,tsv形式のとき
            if( checkPassHead( $text ) == true ){
                //行頭に特定の文字がないとき
                $aryMember []= $text ;
                
            }else{
                //行頭に特定の文字があるとき
                $aryPassMember []= $text;
            }
            
        }else{
            
            //csv,tsv形式じゃないとき
            $aryNotFormat []= $text;
        }
    }
    
    $result = array( $memberDirPath, $aryMember, $aryNotFormat, $aryPassMember );   
    
    return $result;
}


/**
 *  ファイルから抜き出したテキストが正しい形式か確認する
 * （ 名前,メールアドレス、名前, メールアドレス、名前	メールアドレス ）=> true
 *  @author Tomari
 *  @param  string $text 確認するテキスト
 *  @return bool   正しければ true , 間違っていれば false
 */
function checkFormatCsvTsv( $text ){
    return (preg_match("<[0-9a-zA-Zぁ-んァ-ヶ亜-熙]+(,|, |	).+@.+>", $text) == 1) ? true : false;
}


/**
 *  行頭に特定の文字が入っているか確認
 *  確認する文字 =>getPassHeadAry()
 *  @author Tomari
 *  @param  string $text  確認するテキスト
 *  @return bool $result 特定の文字があればfalse , 無ければtrue
 */
function checkPassHead( $text ){   
    $result = true;
    $aryCheckWord = getPassHeadAry();
    
    foreach ( $aryCheckWord as $checkWord ) {
        if( strpos( $text, $checkWord ) === 0 ){
            $result = false;
            break;
        }
    }
    
    return $result; 
}


/**
 *  行頭に付いていたらスキップする文字の配列を返す関数
 *  @author Tomari
 *  @return array 指定する文字の配列
 */
function getPassHeadAry() {
    return array("\t",'/');
}


/**
 *  PHP起動時のオプションを取得する
 *  起動引数が存在するか、またファイルが存在するかを確認する。
 *  @author Tomari
 *  @param  $argv array 起動時のオプション  第1引数 => ファイル名
 *  @return $result string ファイルが存在するならファイル名 存在しないならNULL
 */
function getRunOption( $argv ) {
    $result = NULL;
    
    try {
        if( empty( $argv ) == false ) {
            //引数があるとき
            array_shift( $argv );
            
            foreach( $argv as $str ) {
                if( file_exists( $str ) == true ) {
                    //該当するファイルが存在するとき
                    $result = $str;
                }
                
                break;
            }
        }
        
    }catch ( Exception $e ){
        //エラー発生時はNULLで返す
        $result = NULL;
    }
    
    return $result;
}













/*テスト開始
//元ファイル：test.conf
C:\Users\Tomari\teconewbie2016\www\k_work <= 個人ファイルが入っているディレクトリへのパス
Tomari,tomari.tadahiko@tecotec.co.jp      
Tadahiko  tadahiko.tomari@tecotec.co.jp   
Tomari,                                   
泊忠彦, tadahiko.tomari@tecotec.co.jp 

/とまりただひこ	tadahiko.tomari@tecotec.co.jp
20160401_泊_R&Dセンター第4セクション, tomari.tadahiko@tecotec.co.jp
Ｔｏｍａｒｉ, tomari.tadahiko@tecotec.co.jp,Tomari
	稲澤,inazawa@tecotec.co.jp
//getRunOptionテスト
コマンドライン
$ php filereader.php test.conf     => 成功
$ php filereader.php test          => 失敗( ファイルが存在しないため )
$ php filereader.php               => 失敗( 引数が存在しないため )
$ php filereader.php test test.conf=> 失敗( 第1引数がファイル名ではないため )
//ファイル読み込み結果
  [0]=>
  string(41) "C:\Users\Tomari\teconewbie2016\www\k_work"
   //結果：csv、tsv形式 かつ 行頭に特定の文字がないとき
  [1]=>
  array(4) {
    [0]=>
    string(36) "Tomari,tomari.tadahiko@tecotec.co.jp"
    [1]=>
    string(40) "泊忠彦, tadahiko.tomari@tecotec.co.jp"
    [2]=>
    string(78) "20160401_泊_R&Dセンター第4セクション, tomari.tadahiko@tecotec.co.jp"
    [3]=>
    string(49) "Ｔｏｍａｒｉ, tomari.tadahiko@tecotec.co.jp"
  }
   //結果：csv、tsv形式じゃないとき
  [2]=>
  array(3) {
    [0]=>
    string(39) "Tadahiko  tadahiko.tomari@tecotec.co.jp"
    [1]=>
    string(7) "Tomari,"
    [2]=>
    string(0) ""
  }
   //結果：csv、tsv形式 かつ 行頭に特定の文字があるとき
  [3]=>
  array(2) {
    [0]=>
    string(52) "/とまりただひこ tadahiko.tomari@tecotec.co.jp"
    [1]=>
    string(29) "        稲澤,inazawa@tecotec.co.jp"
  }
} */
//checkFormatCsvTsv テスト
//結果：true
var_dump( checkFormatCsvTsv( "Tomari,tomari.tadahiko@tecotec.co.jp" ) ); 
var_dump( checkFormatCsvTsv( "Tomari, tomari.tadahiko@tecotec.co.jp" ) ); 
var_dump( checkFormatCsvTsv( "Tomari	tomari.tadahiko@tecotec.co.jp" ) ); 
var_dump( checkFormatCsvTsv( "泊タダひこ, tomari.tadahiko@tecotec.co.jp" ) );
var_dump( checkFormatCsvTsv( "Ｔｏｍａｒｉ, tomari.tadahiko@tecotec.co.jp" ) );
var_dump( checkFormatCsvTsv( "20160401_泊_R&Dセンター第4セクション, tomari.tadahiko@tecotec.co.jp" ) );
//結果：false
var_dump( checkFormatCsvTsv( "Tomari:tomari.tadahiko@tecotec.co.jp" ) ); // コロンのため
var_dump( checkFormatCsvTsv( "Tomari," ) ); //メールアドレスがないため
var_dump( checkFormatCsvTsv( "tomari.tadahiko@tecotec.co.jp,Tomari" ) ); //順番が逆なため
//テスト終了