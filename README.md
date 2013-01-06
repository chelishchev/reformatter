reformatter
===========

Класс, который переформировывает массив при работе с выборками sql left join, позволяя накапливать данные, относящиеся к одной сущности

Пример использования
===========

    public function actionBox($restaurant)
    {
        $transformT = array(
            'lunch_box'                 => 'box',
            'lunch_assignment_box_item' => 'ass',
            'lunch_item'                => 'item',
            'lunch_category_item'       => 'item_category',
        );

        $ref = new Reformatter();
        $select = $ref
            ->setTableAlias($transformT)
            ->setColumnAlias('lunch_item', array(
                                                'name_short',
                                                'name_full',
                                                'descr_short',
                                                'descr_full',
                                                'position',
                                           )
                            )
            ->setColumnAlias('lunch_category_item', array('name'))
            ->setColumnAlias('lunch_category_item', array('name'))
            ->setConstColumns('lunch_box', array(
                                               'id',
                                               'name',
                                               'position'
                                          ))
            ->getSelect()
        ;


        $cmd = Y::a()->db->createCommand()
            ->select($select)
            ->from('lunch_box box')
            ->leftJoin('lunch_assignment_box_item ass', 'ass.box_id = box.id')
            ->leftJoin('lunch_item item', 'ass.item_id = item.id')
            ->leftJoin('lunch_category_item item_category', 'item.category_id = item_category.id')
            ->where('box.restaurant_id=:restaurant', array(
                                                        'restaurant' => $restaurant
                                                   ))
            ->order('box.position, box.id') //сортировка по порядку ланчей и по самим ид ланчей, чтобы не прерывалось слияние строк
            ->query()
        ;

        $this->jsonResponse($ref->getResults($cmd));
    }
