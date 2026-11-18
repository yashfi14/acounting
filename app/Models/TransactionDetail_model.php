<?php

class TransactionDetail_model {
    private $table = 'transaction_details';
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    /**
     * Tambah detail item ke transaksi.
     * Item yang dimasukkan sudah divalidasi di controller (harga dari DB).
     */
    public function addDetails($transaction_id, $cartItems)
    {
        $query = "INSERT INTO {$this->table} (transaction_id, product_id, quantity, price, subtotal) 
                  VALUES (:transaction_id, :product_id, :quantity, :price, :subtotal)";

        $this->db->query($query);

        foreach ($cartItems as $item) {
            $this->db->bind('transaction_id', (int)$transaction_id);
            $this->db->bind('product_id',     (int)$item['id']);
            $this->db->bind('quantity',        (int)$item['qty']);
            $this->db->bind('price',           (float)$item['price']);
            $this->db->bind('subtotal',        (float)$item['subtotal']);
            $this->db->execute();
        }

        return true;
    }

    /**
     * Ambil detail transaksi beserta nama barang.
     *
     * Perbaikan keamanan: JOIN dengan tabel transactions untuk memvalidasi
     * bahwa transaction_id benar-benar milik user yang sedang login.
     * Ini mencegah user mengakses detail transaksi milik user lain.
     *
     * @param int $transaction_id ID transaksi
     * @param int $user_id        ID user yang sedang login
     */
    public function getDetailsByTransactionId($transaction_id, $user_id)
    {
        // JOIN dengan transactions untuk memastikan kepemilikan
        $query = "SELECT td.*, COALESCE(p.product_name, '[Barang Dihapus]') AS product_name
                  FROM {$this->table} td
                  JOIN transactions t ON td.transaction_id = t.id AND t.user_id = :user_id
                  LEFT JOIN products p ON td.product_id = p.id
                  WHERE td.transaction_id = :transaction_id";

        $this->db->query($query);
        $this->db->bind('transaction_id', (int)$transaction_id);
        $this->db->bind('user_id',        (int)$user_id);

        return $this->db->resultSet();
    }

    public function deleteDetails($transaction_id)
    {
        $query = "DELETE FROM {$this->table} WHERE transaction_id = :transaction_id";
        $this->db->query($query);
        $this->db->bind('transaction_id', (int)$transaction_id);
        $this->db->execute();
        return $this->db->rowCount();
    }
}
