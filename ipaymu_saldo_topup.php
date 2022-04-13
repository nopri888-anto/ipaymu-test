<?php
require_once 'config.php';



if(isset($_POST['secret_callback'])){
    if(!hash_equals($_POST['secret_callback'],$my_secret_key)){
     
      exit("secret key salah");
    }
}else{
  
  exit("secret key salah");
}


  
  if(empty($_POST['id_user']) or
    empty($_POST['token_topup']) or
    empty($_POST['jumlah_topup']) or
    empty($_POST['total_topup']) or
    !isset($_POST['kode_unik']) or
    !ctype_digit($_POST['jumlah_topup']) or
    !ctype_digit($_POST['kode_unik']) or
    !ctype_digit($_POST['total_topup']) or 
    $_POST['status']!="ok"){
    
    exit("data invalids");
  }
  
  

$koneksi=mysqli_connect($server,$username,$password_sql,$nama_database);
if(!$koneksi){
  exit('Database gagal terkoneksi');
}
  
 
  $id_user=mysqli_real_escape_string($koneksi,$_POST['id_user']);
  $token_topup=mysqli_real_escape_string($koneksi,$_POST['token_topup']);
  $total_topup=mysqli_real_escape_string($koneksi,$_POST['total_topup']);
  
  
  
  if($cek_data_ipaymu=mysqli_query($koneksi,"SELECT * FROM ipaymu_saldo WHERE id_user='$id_user' and token_topup='$token_topup';")){
     
     if(mysqli_num_rows($cek_data_ipaymu)==1){
        $hasil_ipaymu=mysqli_fetch_assoc($cek_data_ipaymu);
        
        
        if($hasil_ipaymu['status']=='paid'){
            exit('<h2>Topup ini telah dibayar</h2><br>Silahkan tekan menu dipojok kanan atas, klik batalkan topup untuk memulai sesi topup yang baru.');
        }else{
           
            
            if(!empty($hasil_ipaymu['url_ipaymu']) and filter_var($hasil_ipaymu['url_ipaymu'], FILTER_VALIDATE_URL)){
                
                $redirect_url=array("url"=>$hasil_ipaymu['url_ipaymu']);
                echo json_encode($redirect_url);
               
                exit();
            }
        }
     }
  }
  
  
  $produk=array();
  $qty=array();
  $harga=array();

  
  $produk[]="TopUp Saldo Rp.".$total_topup;
  $qty[]="1";
  $harga[]=$_POST['jumlah_topup'];


 
  $produk[]="Biaya topup Rp.3500";
  $qty[]="1";
  $harga[]="3500";
  
 
  $body['product']    = $produk;
  $body['qty']        = $qty;
  $body['price']      = $harga;
  $body['returnUrl']  = $berhasil_url;
  $body['cancelUrl']  = $batal_url;
  $body['notifyUrl']  =  $notif_url;

  
  $body['referenceId'] = $token_topup;

  
  $hased_body=strtolower(hash('sha256', json_encode($body,JSON_UNESCAPED_SLASHES)));
  $stringToSign=hash_hmac("sha256","POST:$nomor_va:$hased_body:$api_key_ipaymu",$api_key_ipaymu);

  
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => $url_ipaymu_create,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($body),
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'signature: '.$stringToSign,
        'va: '.$nomor_va,
        'timestamp: '.gmdate('YmdHis')
      ),
    ));

    
    $response = curl_exec($curl);
    curl_close($curl);

    if(!empty($response)){

     
      $json_response=json_decode($response);

     
      if(isset($json_response->Status) and $json_response->Status=="200"){

       
        if(isset($json_response->Data->Url)){
         
          $redirect_url=array("url"=>$json_response->Data->Url);

        
          echo json_encode($redirect_url);
          
          $url_ipaymu=mysqli_real_escape_string($koneksi,$json_response->Data->Url);
          mysqli_query($koneksi,"INSERT INTO `ipaymu_saldo` (`token_topup`, `id_user`, `jumlah_topup`, `url_ipaymu`, `id_trx`, `status`) VALUES ('$token_topup', '$id_user', '$total_topup', '$url_ipaymu', NULL, 'pending');");
       
        }
      }
    }
