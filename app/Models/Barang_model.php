<?php

class Barang_model {
    private $table = 'barang';
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function getAllBarang()
    {
        $this->db->query('SELECT * FROM ' . $this->table . ' ORDER BY id DESC');
        return $this->db->resultSet();
    }

    public function getBarangById($id)
    {
        $this->db->query('SELECT * FROM ' . $this->table . ' WHERE id=:id');
        $this->db->bind('id', $id);
        return $this->db->single();
    }

    public function tambahDataBarang($data)
    {
        $query = "INSERT INTO barang (kode_barang, nama_barang, stok, harga) 
                  VALUES (:kode_barang, :nama_barang, :stok, :harga)";
        
        $this->db->query($query);
        $this->db->bind('kode_barang', $data['kode_barang']);
        $this->db->bind('nama_barang', $data['nama_barang']);
        $this->db->bind('stok', $data['stok']);
        $this->db->bind('harga', $data['harga']);

        $this->db->execute();
        return $this->db->rowCount();
    }

    public function hapusDataBarang($id)
    {
        $query = "DELETE FROM barang WHERE id = :id";
        $this->db->query($query);
        $this->db->bind('id', $id);

        $this->db->execute();
        return $this->db->rowCount();
    }

    public function ubahDataBarang($data)
    {
        $query = "UPDATE barang SET
                    kode_barang = :kode_barang,
                    nama_barang = :nama_barang,
                    stok = :stok,
                    harga = :harga
                  WHERE id = :id";
        
        $this->db->query($query);
        $this->db->bind('kode_barang', $data['kode_barang']);
        $this->db->bind('nama_barang', $data['nama_barang']);
        $this->db->bind('stok', $data['stok']);
        $this->db->bind('harga', $data['harga']);
        $this->db->bind('id', $data['id']);

        $this->db->execute();
        return $this->db->rowCount();
    }
}
