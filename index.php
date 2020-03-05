<?php
/*
 * в этом api нельзя передать несколько id авторов и получить массив одним запросом.
 * только по одному или список всех с пагинацией. С книгами так можно, с авторами нельзя.
 * при получении книг поле TOTAL, которое находится в DATA->META и должно показывать общее количество книг по завпросу
 * периодически показывает 0, хотя в ITEMS книги присутствуют. Это баг. Полагаться на поле TOTAL не буду.
 *
 * Вывод в books.csv
 * разделитель - запятая
 * кодировка UTF-8
 */

# минимальная доставка в пунктах выдачи book24
# у всех товаров она одинаковая
$delivery = file_get_contents('https://book24.ru/local/components/book24/widget.product.card/ajax/delivery_info.php?product_id=5569356&available=&preorder=1');
$book24 = json_decode($delivery)->DATA->delivery_pickup_book24_text;
$book24 = explode(' ', explode(' руб.', $book24)[0]);
$book24_price = $book24[count($book24)-1] . ' руб.';


$section_id = 1361; // категория 'программирование'
$page = 1;

function get_books ($section_id, $page) {
    $books = json_decode(file_get_contents('https://api.book24.ru/api/v1/catalog/lists/products/?FILTER=section_id='.$section_id.'&PAGE='.$page));
    return $books->DATA->ITEMS;
}

function get_authors ($ids) {
    $authors = [];
    foreach ($ids as $id) {
        $authors[] = json_decode(file_get_contents('https://api.book24.ru/api/v1/catalog/lists/authors/'.$id))->DATA->NAME;
    }
    return $authors;
}

function get_all_authors () {
    return json_decode(file_get_contents('https://api.book24.ru/api/v1/catalog/lists/authors/'));
}

$books = [];

while (count($books_by_api = get_books($section_id, $page++)) > 0) {
    $books = array_merge($books, $books_by_api);
}

foreach ($books as $book) {
    $book->authors = get_authors($book->AUTHOR_ID);
}

echo $style = <<<HTML
<style>
html, body {width:100%; overflow-x: hidden;}
table {width:90%; margin:0 auto;}
td, th {border: 1px solid black; padding: 20px;}
th.price {min-width: 130px;}
</style>
HTML;

$fp = fopen('books.csv', 'w');
echo '<table>';
echo $table_head = <<<HTML
<tr>
    <th>Авторы</th>
    <th>Название</th>
    <th class="price">Цена</th>
    <th>Год издания</th>
    <th>Изображение</th>
    <th>Стоимость доставки</th>
</tr>
HTML;

fputcsv($fp, array('Авторы', 'Название', 'Цена', 'Год издания', 'Изображение', 'Стоимость доставки'));

foreach ($books as $book) {
    echo '<tr>';
    echo '<td>'.implode(', ', $book->authors).'</td>';
    echo '<td>'.$book->NAME.'</td>';
    echo '<td>'.$book->PRICE_FORMAT.'</td>';
    echo '<td>'.$book->YEAR.'</td>';
    echo '<td><img width="300" src="'.($img_src = (@$book->IMAGES[0]->SRC) ? @$book->IMAGES[0]->SRC : 'https://cdn.book24.ru/cdn2014/no_pic_book24.jpg').'"></td>';
    echo '<td>От '.$book24_price.'</td>';
    echo '<tr>';

    $to_csv = array(
        implode(', ', $book->authors),
        $book->NAME,
        $book->PRICE_FORMAT,
        $book->YEAR,
        $img_src,
        'От '.$book24_price
    );

    fputcsv($fp, $to_csv);
}
echo '</table>';

fclose($fp);
