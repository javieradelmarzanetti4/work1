<?php

class Newss extends Model
{
    public function getListIndex($limit = 5, $order = 'date_news')
    {
        $sql = "SELECT a.*, cc.category_name FROM news AS a ";
        $sql .= " left join category cc on cc.id_category = a.id_category ";
        $sql .= " WHERE a.is_published = 1 and a.id_news IN (SELECT id_news FROM news AS b ";
        $sql .= " WHERE b.is_published = 1 and b.id_category = a.id_category AND (SELECT COUNT(*) FROM news AS c WHERE c.is_published = 1 and c.id_news >= b.id_news AND c.id_category = b.id_category) <= $limit) ";
        $sql .= " order by {$order} desc, a.id_category";

        return $this->db->query($sql);
    }

    public function getCarouselSlider()
    {
        $sql = "SELECT * FROM news WHERE is_published = 1 ORDER BY date_news DESC";

        return $this->db->query($sql);
    }

    public function getCountPages($limit = 5, $from = 'news')
    {
        $sql = "select count(*) as COUNT from {$from}";
        if (Session::get('login') != 'admin') {
            $sql .= " where is_published=1";
        }
        $count_news = $this->db->query($sql);
        $total_rows = ($count_news[0]['COUNT']);
        $num_pages = ceil($total_rows / $limit);
        return $num_pages;
    }

    public function count_visit($id_news)
    {
        /**
         * 1.узнаем айпи кто зашел
         * 2.выбираем все записи  с лога по айди_новости где айпи = айпи и время 24 часа
         * 3.если нету
         * 4.делаем апдейт счетчика в news
         * 5.вставляем в лог запись
         */
        $visitor_ip = $_SERVER['REMOTE_ADDR'];
//        $date = date("Y-m-d H:i:s");
        /**
         * проверяет в таблице log, были ли посещения данной странички с данного IP адреса за последние 24 часа
         */
        $sql = ("SELECT count(id_news)as count FROM log WHERE id_news='{$id_news}' and 
              ip_visit=INET_ATON('{$visitor_ip}') and  date_visit > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1");

        $count_id = $this->db->query($sql);
        if ($count_id[0]['count'] == 0) {
            $sql = ("UPDATE news SET cnt_visit=(cnt_visit+1) WHERE id_news='{$id_news}'");
            $this->db->query($sql);
        }
        /**
         * добавляет в таблицу log новую запись с id посещенной страницы, IP адресом посетителя и временем посещения.
         */
        $sql = ("INSERT INTO log (id_news,ip_visit)
             VALUES ('{$id_news}',INET_ATON('{$visitor_ip}'))");
        $this->db->query($sql);
    }

    public function getNewsListById($id)
    {
        $this->count_visit($id);
        $sql = "select n.*,t1.id_tag,t1.tag_name from news n
                left join tag_news t on t.id_news=n.id_news
                left join tags t1 on t1.id_tag=t.id_tag 
                where n.id_news={$id}";
        if (Session::get('login') != 'admin') {
            $sql .= " and n.is_published=1";
        }
        $content = $this->db->query($sql);
        $content = array_pop($content);

        if (!Session::get('login') && $content['is_analitic'] == 1) {
            $content['content_news'] = $this->is_analitic($content['content_news']);
        }
        if ($tags = $this->is_tags($id)) {
            $content['tags'] = $tags;
        }

        return $content;
    }

    public function ajax($search)
    {
        $sql = "SELECT * FROM tags WHERE tag_name LIKE '%{$search}%'";

        return $this->db->query($sql);
    }

    public function getTagsList()
    {
        $sql = "select *,(select count(*) from tags) as count from tags ";
        $result = $this->db->query($sql);

        for ($i = 0; $i < count($result); $i++) {
            $results[$result[$i]['id_tag']] = $result[$i]['tag_name'];
        }

        return $results;
    }

    public function is_tags($id)
    {
        $id = (int)$id;
        $sql = "SELECT  t.id_tag,t1.tag_name from news n
                right join tag_news t on t.id_news=n.id_news
                left join tags t1 on t1.id_tag=t.id_tag
                where n.id_news='{$id}' ";
        $result = $this->db->query($sql);

        if ($result) {
            for ($i = 0; $i < count($result); $i++) {
                $results[$result[$i]['id_tag']] = $result[$i]['tag_name'];
            }

            return $results;
        }

        return false;
    }


    public function is_analitic($content)
    {
        preg_match('/^([^.!?]*[\.!?]+){0,5}/u', strip_tags($content), $sentences);

        return $sentences[0];
    }

    public function getNewsListByTagId($id, $page = 0, $limit = 10)
    {
        $start = $page * $limit;
        $sql = "select n.*,t1.id_tag,t1.tag_name from news n
                left join tag_news t on t.id_news=n.id_news
                left join tags t1 on t1.id_tag=t.id_tag 
                where t1.id_tag={$id} and n.is_published=1 order by date_news desc limit {$start},{$limit}";
        $result = $this->db->query($sql);

        return ($result);
    }

    public function getNewsByFilter($data)
    {
        if (!$data['date_ot']) {
            $sql = "select date(min(n.date_news)) as dat from news n where n.is_published=1";
            $date = $this->db->query($sql);
            $data['date_ot'] = $date[0]['dat'];
        }
        if (!$data['date_do']) {
            $sql = "select date(max(n.date_news)) as dat from news n where n.is_published=1";
            $date = $this->db->query($sql);
            $data['date_do'] = $date[0]['dat'];
        }
        $sql = "select distinct  n.* from news n
              left join tag_news t on t.id_news=n.id_news
              where n.is_published=1 and date(date_news)between '{$data['date_ot']}' and '{$data['date_do']}'";
        if (isset($data['tags'])) {
            $tmp = null;
            foreach ($data['tags'] as $key => $tag) {
                $tmp .= $key . ',';
            }
            $data['tags'] = substr($tmp, 0, -1);
            $sql .= " and t.id_tag in({$data['tags']})";
        }
        if (isset($data['category'])) {
            $tmp = null;
            foreach ($data['category'] as $key => $tag) {
                $tmp .= $key . ',';
            }
            $data['category'] = substr($tmp, 0, -1);
            $sql .= " and n.id_category in({$data['category']})";
        }

        return $this->db->query($sql);
    }

    public function getCategoryList()
    {
        $sql = "SELECT * FROM category ";
        $result = $this->db->query($sql);
        for ($i = 0; $i < count($result); $i++) {
            $results[$result[$i]['id_category']] = $result[$i]['category_name'];
        }

        return $results;
    }

    public function getAnalyticsList()
    {
        $sql = "select * from news 
                  where is_analitic = 1 and is_published = 1 
                  order by date_news desc";

        return $this->db->query($sql);
    }

    public function getAnalyticsListLimit($start = 0, $limit = 5)
    {
        $sql = "select * from news 
                  where is_analitic = 1 and is_published = 1 
                  order by date_news desc 
                  limit {$start},{$limit}";

        return $this->db->query($sql);
    }

    public function getAnalyticsByPage($page = 0, $limit = 5)
    {
        $start = $page * $limit;
        $sql = "select * from news 
                  where is_analitic = 1 and is_published = 1 
                  order by date_news desc 
                  limit {$start},{$limit}";
        $result = $this->db->query($sql);
        $result['count'] = $this->cnt_analytics($limit);

        return $result;
    }

    public function cnt_analytics($limit = 5)
    {
        $sql = "select count(*) as COUNT from news 
                  where is_analitic = 1 and is_published = 1";
        $count_analytics = $this->db->query($sql);
        $total_rows = ($count_analytics[0]['COUNT']);
        $num_pages = ceil($total_rows / $limit);

        return $num_pages;
    }

    public function getAnalyticsData($id = 6)
    {
        $sql = "select * from category where id_category = {$id}";

        return $this->db->query($sql);
    }

    public function getCategoryById($id)
    {
        $id = (int)$id;
        $sql = "select * from news n
                left join category c on c.id_category=n.id_category 
                where c.id_category={$id}";
        $result = $this->db->query($sql);

        return $result;
    }

    public function admin_add_category($categories_name)
    {
        $sql = "insert into category (`category_name`) VALUES {$categories_name}";

        return $this->db->query($sql);
    }

    public function admin_add_tag($tags_name)
    {
        $sql = "insert into tags (`tag_name`) VALUES {$tags_name}";

        return $this->db->query($sql);
    }

    public function saveTag($tags, $id_news = null)
    {
        $cnt_tags = count($tags);
        $sql = "delete from tag_news where id_news='{$id_news}'";
        $this->db->query($sql);
        for ($i = 0; $i < $cnt_tags; $i++) {
            $sql = "insert into tag_news
                    set id_news='{$id_news}',
                    id_tag='{$tags[$i]}'";
            $this->db->query($sql);
        }
    }

    public function getNewsListByPage($page = 0, $limit = 10)
    {
        $start = $page * $limit;
        $sql = "select n.*,category_name from news n
                left join category c on c.id_category=n.id_category ";
        if (Session::get('login') != 'admin') {
            $sql .= "where n.is_published=1";
        }
        $sql .= " order by date_news desc limit {$start},{$limit}";
        $result = $this->db->query($sql);
        $result['count'] = $this->getCountPages($limit);

        return ($result);
    }

    public function move_uploaded_file($file)
    {
        if ($file['photo']['size'] <= 0 || $file['photo']['size'] > 3 * pow(10,6)) {
            return false;
        }
        if (!$file['photo']['type'] || !in_array($file['photo']['type'], ALLOW_TYPES)) {
            return false;
        }

        $ext = '';
        for ($i = 0; $i < count(ALLOW_TYPES); $i++) {
            if ($file['photo']['type'] != ALLOW_TYPES[$i]) continue;
            if (ALLOW_TYPES[$i] == 'image/jpeg') $ext = '.jpg';
            if (ALLOW_TYPES[$i] == 'image/png') $ext = '.png';
            if (ALLOW_TYPES[$i] == 'image/gif') $ext = '.gif';
        }

        $uploads_dir = ROOT . DS . IMAGE_PATH;
        $tmp_name = $file['photo']['tmp_name'];
        $name = time() . '_' . uniqid() . $ext;
        move_uploaded_file($tmp_name, $uploads_dir . DS . $name);

        return $name;
    }

    public function save($data, $image, $id = null)
    {
        if (!isset($data['id_category']) || !isset($data['title_news']) || !isset($data['content_news'])) {
            return false;
        }
        $id = (int)$id;
        $id_category = $this->db->escape($data['id_category']);
        $title = $this->db->escape($data['title_news']);
        $content = $this->db->escape($data['content_news']);
        $is_published = isset($data['is_published']) ? 1 : 0;
        $is_analitic = isset($data['is_analitic']) ? 1 : 0;

        if (!$id) {
            $sql = "
            insert into news
            set id_category='{$id_category}',
                title_news='{$title}',
                content_news='{$content}',
                is_published='{$is_published}',
                is_analitic='{$is_analitic}',
                image_news='{$image}'
            ";
            $result = $this->db->query($sql);
            $id = $this->db->last_id();
        } else {
            $sql = "update news
            set id_category='{$id_category}',
                title_news='{$title}',
                content_news='{$content}',
                is_published='{$is_published}',
                is_analitic='{$is_analitic}'";
            if ($image) {
                $sql .= ", image_news='{$image}' ";
            }
            $sql .= " where id_news={$id} ";
            $result = $this->db->query($sql);
        }

        if (isset($data['tags'])) {
            $this->saveTag($data['tags'], $id);
        }
        return $result;
    }

    public function delete($id)
    {
        $id = (int)$id;
        $sql = "delete from news where id_news = {$id}";

        return $this->db->query($sql);
    }

    public function delete_image($id)
    {
        $id = (int)$id;
        $sql = "select image_news from news where id_news = {$id}";

        return $this->db->query($sql);
    }

    public function delete_tag($id)
    {
        $id = (int)$id;
        $sql = "delete from tags where id_tag = {$id}";

        return $this->db->query($sql);
    }

    public function delete_category($id)
    {
        $id = (int)$id;
        $sql = "delete from category where id_category = {$id}";

        return $this->db->query($sql);
    }
}