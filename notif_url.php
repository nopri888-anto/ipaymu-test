<?php
require_once 'config.php';

if(isset($_POST['trx_id']) and isset($_POST['sid']) and  isset($_POST['status']) and $_POST['status']=="berhasil"){


  $transaksi_id=$_POST['trx_id'];


  $body=json_encode(array('transactionId' => $transaksi_id),JSON_UNESCAPED_SLASHES);
  $hased_body=strtolower(hash('sha256', $body));
  $stringToSign=hash_hmac("sha256","POST:$nomor_va:$hased_body:$api_key_ipaymu",$api_key_ipaymu);

  
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => $url_ipaymu_check,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'signature: '.$stringToSign,
      'va: '.$nomor_va,
      'timestamp: '.gmdate('YmdHis')
    ),
  ));
 
 
  $response = curl_exec($curl);

  curl_close($curl);

  
  $json_response=json_decode($response);

  
  if($json_response->Status=="200" and isset($json_response->Data)){

    if($json_response->Data->Status=="1" or $json_response->Data->Status=="6"){

    
      $koneksi=mysqli_connect($server,$username,$password_sql,$nama_database);
      if(!$koneksi){
        exit('Database gagal terkoneksi');
      }
     
     
      $token_topup=mysqli_real_escape_string($koneksi,$json_response->Data->ReferenceId);
      $transaksi_id = mysqli_real_escape_string($koneksi,$transaksi_id);
     
    if($cek_data_topup=mysqli_query($koneksi,"SELECT * FROM ipaymu_saldo WHERE token_topup='$token_topup';")){
       if(mysqli_num_rows($cek_data_topup) > 0){
           
         $row_ipaymu = mysqli_fetch_assoc($cek_data_topup);
         if($row_ipaymu['status']=='pending'){
             mysqli_query($koneksi,"UPDATE `ipaymu_saldo` SET `status` = 'paid',`id_trx`='$transaksi_id' WHERE `token_topup` = '$token_topup';");
         }else{
             exit("Data status telah paid");
         }
         
       }else{
          exit("Data tidak ditemukan");
       }
    }else{
         exit("Data tidak ditemukan");
    }
      
      

     
      $header=  array("Authorization: Bearer ".$api_key_bukaolshop );      

      $post_body=array(
        "token_topup"=>$token_topup,
      );

     
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL,"https://bukaolshop.net/api/v1/member/topup");
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $hasil = curl_exec($ch);
      curl_close ($ch);


     echo $hasil;

    }
  }
}


?>
