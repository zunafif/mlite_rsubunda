<?php
namespace Plugins\Icd;

use Systems\AdminModule;
use Systems\MySQL;
use Plugins\Icd\DB_ICD;

class Admin extends AdminModule
{

  public function navigation()
  {
      return [
          'Kelola'   => 'manage',
      ];
  }

  public function getManage()
  {
      $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
      // JS
      $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'), 'footer');
      $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'), 'footer');
      return $this->draw('manage.html');
  }

  public function getICD10()
  {
    $rows_icd10 = $this->data_icd('icd10')->toArray();
    $return_array = array('data'=> $rows_icd10);
    echo json_encode($return_array);
    exit();
  }

  public function getICD9()
  {
    $rows_icd9 = $this->data_icd('icd9')->toArray();
    $return_array = array('data'=> $rows_icd9);
    echo json_encode($return_array);
    exit();
  }

  public function postICD9()
  {

    if(isset($_POST["query"])){
      $output = '';
      $key = "%".$_POST["query"]."%";
      $rows = $this->data_icd('icd9')->like('kode', $key)->orLike('nama', $key)->asc('nama')->limit(10)->toArray();
      $output = '';
      if(count($rows)){
        foreach ($rows as $row) {
          $output .= '<li class="list-group-item link-class">'.$row["kode"].': '.$row["nama"].'</li>';
        }
      } else {
        $output .= '<li class="list-group-item link-class">Tidak ada yang cocok.</li>';
      }
      echo $output;
    }

    exit();

  }

  public function postICD10()
  {

    if(isset($_POST["query"])){
      $output = '';
      $key = "%".$_POST["query"]."%";
      $rows = $this->data_icd('icd10')->like('kode', $key)->orLike('nama', $key)->asc('nama')->limit(10)->toArray();
      $output = '';
      if(count($rows)){
        foreach ($rows as $row) {
          $output .= '<li class="list-group-item link-class">'.$row["kode"].': '.$row["nama"].'</li>';
        }
      } else {
        $output .= '<li class="list-group-item link-class">Tidak ada yang cocok.</li>';
      }
      echo $output;
    }

    exit();

  }

  public function postSaveICD9()
  {
    if(!$this->core->mysql('icd9')->where('kode', $_POST['kode'])->oneArray()){
      $this->core->mysql('icd9')->save([
        'kode' => $_POST['kode'],
        'deskripsi_panjang' => $_POST['nama'],
        'deskripsi_pendek' => $_POST['nama']
      ]);
    }
    unset($_POST['nama']);
    $this->core->mysql('prosedur_pasien')->save($_POST);
    exit();
  }

  public function postSaveICD10()
  {
    if(!$this->core->mysql('penyakit')->where('kd_penyakit', $_POST['kd_penyakit'])->oneArray()){
      $this->core->mysql('penyakit')->save([
        'kd_penyakit' => $_POST['kd_penyakit'],
        'nm_penyakit' => $_POST['nama'],
        'ciri_ciri' => '-',
        'keterangan' => '-',
        'kd_ktg' => '-',
        'status' => 'Tidak Menular'
      ]);
    }
    $_POST['status_penyakit'] = 'Baru';
    //if($this->core->mysql('diagnosa_pasien')->where('kd_penyakit', $_POST['kd_penyakit'])->oneArray()){
    //  $_POST['status_penyakit'] = 'Lama';
    //}
    unset($_POST['nama']);
    $this->core->mysql('diagnosa_pasien')->save($_POST);
    exit();
  }

  public function getDisplay()
  {
    $no_rawat = $_GET['no_rawat'];
    $prosedurs = $this->core->mysql('prosedur_pasien')
      ->where('no_rawat', $no_rawat)
      ->asc('prioritas')
      ->toArray();
    $prosedur = [];
    foreach ($prosedurs as $row_prosedur) {
      $icd9 = $this->core->mysql('icd9')->where('kode', $row_prosedur['kode'])->oneArray();
      $row_prosedur['nama'] = $icd9['deskripsi_panjang'];
      $prosedur[] = $row_prosedur;
    }

    $diagnosas = $this->core->mysql('diagnosa_pasien')
      ->where('no_rawat', $no_rawat)
      ->asc('prioritas')
      ->toArray();
    $diagnosa = [];
    foreach ($diagnosas as $row_diagnosa) {
      $icd10 = $this->core->mysql('penyakit')->where('kd_penyakit', $row_diagnosa['kd_penyakit'])->oneArray();
      $row_diagnosa['nama'] = $icd10['nm_penyakit'];
      $diagnosa[] = $row_diagnosa;
    }

    echo $this->draw('display.html', ['diagnosa' => $diagnosa, 'prosedur' => $prosedur]);
    exit();
  }

  public function postHapusICD10()
  {
    $this->core->mysql('diagnosa_pasien')->where('no_rawat', $_POST['no_rawat'])->where('prioritas', $_POST['prioritas'])->delete();
    exit();
  }

  public function postHapusICD9()
  {
    $this->core->mysql('prosedur_pasien')->where('no_rawat', $_POST['no_rawat'])->where('prioritas', $_POST['prioritas'])->delete();
    exit();
  }

  public function postSimpan_ICD9()
  {
    $dbFile = BASE_DIR.'/systems/data/icd.sdb';
    $db = new \PDO('sqlite:'.$dbFile);
    if($_POST['simpan_icd9']) {
      $cek = $this->data_icd('icd9')->where('kode', $_POST['kode_icd9'])->oneArray();
      if(!$cek) {
        $db->query("INSERT INTO icd9 (kode,nama) VALUES ('{$_POST['kode_icd9']}', '{$_POST['nama_icd9']}')");
      } else {
        $db->query("UPDATE icd9 SET nama='{$_POST['nama_icd9']}' WHERE kode='{$_POST['kode_icd9']}'");
      }
      $this->notify('success', 'Data ICD telah disimpan');
    }
    redirect(url([ADMIN, 'icd', 'manage']));
  }

  public function postSimpan_ICD10()
  {
    $dbFile = BASE_DIR.'/systems/data/icd.sdb';
    $db = new \PDO('sqlite:'.$dbFile);
    if($_POST['simpan_icd10']) {
      $cek = $this->data_icd('icd10')->where('kode', $_POST['kode_icd10'])->oneArray();
      if(!$cek) {
        $db->query("INSERT INTO icd10 (kode,nama) VALUES ('{$_POST['kode_icd10']}', '{$_POST['nama_icd10']}')");
      } else {
        $db->query("UPDATE icd10 SET nama='{$_POST['nama_icd10']}' WHERE kode='{$_POST['kode_icd10']}'");
      }
      $this->notify('success', 'Data ICD telah disimpan');
    }
    redirect(url([ADMIN, 'icd', 'manage']));
  }

  protected function data_icd($table)
  {
      return new DB_ICD($table);
  }

}
