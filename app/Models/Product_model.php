<?php

class Product_model {
    private $table = 'products';
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function getAllProducts($user_id, $limit, $offset, $keyword = '', $onlyActive = false)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id";
        
        if ($onlyActive) {
            $query .= " AND is_active = 1";
        }

        if (!empty($keyword)) {
            $query .= " AND product_name LIKE :keyword";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $this->db->query($query);
        $this->db->bind('user_id', (int)$user_id);
        
        if (!empty($keyword)) {
            $this->db->bind('keyword', "%{$keyword}%");
        }
        
        $this->db->bind('limit', (int)$limit, PDO::PARAM_INT);
        $this->db->bind('offset', (int)$offset, PDO::PARAM_INT);

        return $this->db->resultSet();
    }

    public function countProducts($user_id, $keyword = '', $onlyActive = false)
    {
        $query = "SELECT COUNT(id) as total FROM " . $this->table . " WHERE user_id = :user_id";
        
        if ($onlyActive) {
            $query .= " AND is_active = 1";
        }

        if (!empty($keyword)) {
            $query .= " AND product_name LIKE :keyword";
        }
        
        $this->db->query($query);
        $this->db->bind('user_id', (int)$user_id);
        
        if (!empty($keyword)) {
            $this->db->bind('keyword', "%{$keyword}%");
        }

        $result = $this->db->single();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Mengambil daftar produk untuk katalog publik pelanggan
     * Hanya menampilkan produk yang is_active = 1
     */
    public function getPublicCatalog($business_id = null, $limit = 20, $offset = 0, $keyword = '')
    {
        $query = "SELECT id, product_name, stock, price, image, is_active FROM " . $this->table . " WHERE is_active = 1";

        if ($business_id !== null && $business_id > 0) {
            $query .= " AND user_id = :business_id";
        }

        if (!empty($keyword)) {
            $query .= " AND product_name LIKE :keyword";
        }

        $query .= " ORDER BY product_name ASC LIMIT :limit OFFSET :offset";

        $this->db->query($query);
        if ($business_id !== null && $business_id > 0) {
            $this->db->bind('business_id', (int)$business_id);
        }
        if (!empty($keyword)) {
            $this->db->bind('keyword', "%{$keyword}%");
        }
        $this->db->bind('limit', (int)$limit, PDO::PARAM_INT);
        $this->db->bind('offset', (int)$offset, PDO::PARAM_INT);

        return $this->db->resultSet();
    }

    public function countPublicCatalog($business_id = null, $keyword = '')
    {
        $query = "SELECT COUNT(id) as total FROM " . $this->table . " WHERE is_active = 1";

        if ($business_id !== null && $business_id > 0) {
            $query .= " AND user_id = :business_id";
        }

        if (!empty($keyword)) {
            $query .= " AND product_name LIKE :keyword";
        }

        $this->db->query($query);
        if ($business_id !== null && $business_id > 0) {
            $this->db->bind('business_id', (int)$business_id);
        }
        if (!empty($keyword)) {
            $this->db->bind('keyword', "%{$keyword}%");
        }

        $row = $this->db->single();
        return (int)($row['total'] ?? 0);
    }

    public function getProductById($id, $user_id)
    {
        $this->db->query('SELECT * FROM ' . $this->table . ' WHERE id = :id AND user_id = :user_id');
        $this->db->bind('id', (int)$id);
        $this->db->bind('user_id', (int)$user_id);
        return $this->db->single();
    }

    public function addProduct($data)
    {
        $name     = $data['name'] ?? $data['product_name'] ?? '';
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $image    = !empty($data['image']) ? $data['image'] : null;

        $query = "INSERT INTO " . $this->table . " (user_id, product_name, stock, price, is_active, image) 
                  VALUES (:user_id, :name, :stock, :price, :is_active, :image)";
        
        $this->db->query($query);
        $this->db->bind('user_id', (int)$data['user_id']);
        $this->db->bind('name', $name);
        $this->db->bind('stock', (int)$data['stock']);
        $this->db->bind('price', $data['price']);
        $this->db->bind('is_active', $isActive);
        $this->db->bind('image', $image);

        $this->db->execute();
        return $this->db->lastInsertId() ?: $this->db->rowCount();
    }

    public function updateProduct($data)
    {
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $image    = isset($data['image']) ? $data['image'] : null;

        $query = "UPDATE " . $this->table . " SET
                    product_name = :name,
                    stock = :stock,
                    price = :price,
                    is_active = :is_active";
        
        if ($image !== null) {
            $query .= ", image = :image";
        }

        $query .= " WHERE id = :id AND user_id = :user_id";
        
        $this->db->query($query);
        $this->db->bind('name', $data['name']);
        $this->db->bind('stock', (int)$data['stock']);
        $this->db->bind('price', $data['price']);
        $this->db->bind('is_active', $isActive);
        $this->db->bind('id', (int)$data['id']);
        $this->db->bind('user_id', (int)$data['user_id']);

        if ($image !== null) {
            $this->db->bind('image', $image);
        }

        $this->db->execute();
        return $this->db->rowCount();
    }

    public function deleteProduct($id, $user_id)
    {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        $this->db->query($query);
        $this->db->bind('id', (int)$id);
        $this->db->bind('user_id', (int)$user_id);

        $this->db->execute();
        return $this->db->rowCount();
    }

    public function updateStock($id, $user_id, $quantity)
    {
        $query = "UPDATE " . $this->table . " 
                  SET stock = stock - :quantity1 
                  WHERE id = :id AND user_id = :user_id AND stock >= :quantity2";
                  
        $this->db->query($query);
        $this->db->bind('quantity1', (int)$quantity);
        $this->db->bind('quantity2', (int)$quantity);
        $this->db->bind('id', (int)$id);
        $this->db->bind('user_id', (int)$user_id);
        
        $this->db->execute();
        return $this->db->rowCount();
    }

    public function restoreStock($id, $user_id, $quantity)
    {
        $query = "UPDATE " . $this->table . " 
                  SET stock = stock + :quantity 
                  WHERE id = :id AND user_id = :user_id";
                  
        $this->db->query($query);
        $this->db->bind('quantity', (int)$quantity);
        $this->db->bind('id', (int)$id);
        $this->db->bind('user_id', (int)$user_id);
        
        $this->db->execute();
        return $this->db->rowCount();
    }
}
