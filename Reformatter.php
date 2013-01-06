<?php
/**
 * Класс, который переформировывает массив
 * при работе с выборками sql left join, позволяя накапливать данные,
 * относящиеся к одной сущности
 *
 * @property array $tableAlias
 * @property array $columnAlias
 * @property array $constFields
 * @property array $constVar
 */
class Reformatter extends CComponent
{
    /**
     * Таблица - альяс таблицы
     * @var array
     */
    protected $_tableAlias = array();
    /**
     * Таблица - колонка - альяс колонки
     * @var array
     */
    protected $_columnAlias = array();
    /**
     * Колонки
     * В выборке данные колонке не будут формироваться как остальные,
     * чтобы не накапливаться
     * указываем ключи, значения которых не надо накапливать
     * @var array
     */
    protected $_constColumns = array();
    /**
     * Имя таблицы, в которой указаны колонки self::$_constColumns
     * @var array
     */
    protected $_tableConstColumns = array();

    protected $_constVar = 'id';

    public function setConstVar($constVar)
    {
        $this->_constVar = $constVar;

        return $this;
    }

    public function getConstVar()
    {
        return $this->_constVar;
    }


    public function getSelect()
    {
        $select = array();
        foreach($this->_tableAlias as $table => $aliasTable)
        {
            if($table === $this->_tableConstColumns)
            {
                foreach($this->_constColumns as $column => $aliasColumn)
                {
                    $select[] = $aliasTable . '.' . $column . ' ' . $aliasColumn;
                }
                unset($column, $aliasColumn);
            }

            //если для этой таблицы есть колонки, то перебираем их
            if(!array_key_exists($table, $this->_columnAlias))
            {
                continue;
            }
            foreach($this->_columnAlias[$table] as $column => $aliasColumn)
            {
                $select[] = $aliasTable . '.' . $column . ' ' . $aliasTable . '_' . $aliasColumn;
            }
            unset($column, $aliasColumn);
        }

        return implode(', ', $select);
    }

    /**
     * Поля, которые не нужно включать в иерархию, то есть не делать префиксы из альясов
     * @param $table
     * @param array $constFields
     * @return Reformatter
     */
    public function setConstColumns($table, array $constFields)
    {
        $this->_tableConstColumns = $table;
        $this->_constColumns      = $this->rebuildAliasArray($constFields);

        return $this;
    }

    public function getConstColumns()
    {
        return $this->_constColumns;
    }

    /**
     * @param $table
     * @param array $columnAlias
     * @return $this
     */
    public function setColumnAlias($table, array $columnAlias)
    {
        $this->_columnAlias[$table] = $this->rebuildAliasArray($columnAlias);

        unset($columnAlias, $table);

        return $this;
    }

    public function getColumnAlias()
    {
        return $this->_columnAlias;
    }

    /**
     * @param array $tables
     * @return $this
     */
    public function setTableAlias(array $tables)
    {
        $this->_tableAlias = $this->rebuildAliasArray($tables);

        unset($tables);

        return $this;
    }

    public function getTableAlias()
    {
        return $this->_tableAlias;
    }

    /**
     * Слияние строк в первый аргумент. При этом выполняется преобразование значений $mergeRow в массив, если
     * ключи этих значений не входят в $constFields.
     * @param array $row
     * @param array $mergeRow
     * @param array $constFields
     * @return void
     * @author Ivan Chelishchev <chelishchev@gmail.com>
     */
    protected function mergeRow(array &$row, array $mergeRow, array $constFields)
    {
        $this->wrapItemsOfArray($mergeRow, $constFields);
        $row = CMap::mergeArray($row, $mergeRow);
    }

    /**
     * Обходим массив и элементы, ключи которых не принадлежат $constFields, будут преобразованы к массиву
     * чтобы потом можно было всё удобно слить для накапливания
     * @param array $row
     * @param array $constFields
     */
    protected function wrapItemsOfArray(array &$row, array $constFields = array())
    {
        array_walk($row, function(&$item, $key) use($constFields){
                if(!in_array($key, $constFields))
                {
                    $item = array($item);
                }
            });
    }

    /**
     * Переформативароние устройства массива путем разбора ключей массива, как путей, для создания вложенных
     * массивов.
     *
     *         $a = array(
     *            'id' => 1,
     *            'label_name' => 'labelName',
     *            'label_user_name' => 'userName',
     *            'source_user_id' => 'userid'
     *        );
     * Станет
     *         $a = array(
     *            'id'              => 1,
     *            'label'           => array(
     *                'name' => 'labelName',
     *                'user' => array(
     *                    'name' => 'userName'
     *                )
     *            ),
     *            'source'          => array(
     *                'user' => array(
     *                    'id' => 'userid'
     *                ),
     *            ),
     *       );
     *
     * @param array $array
     * @param string $delimiter
     * @author Ivan Chelishchev <chelishchev@gmail.com>
     */
    protected function reformatArray(array &$array, $delimiter = '_')
    {
        foreach ($array as $key => $item)
        {
            //разделяем ключ, чтобы получить путь
            $keyPath = explode($delimiter, trim($key, $delimiter));
            //если это действительно путь, то пойдем создавать вложенные массивы
            if(count($keyPath) > 1)
            {
                //запоминаем последний ключ, чтобы знать, что это конец
                $lastKey = array_pop($keyPath);
                //здесь будет храниться ссылка на массив
                $linkNew = null;
                //перебор элементов пути
                foreach ($keyPath as $_key)
                {
                    //если не последний и ссылка ещё не была создана
                    if(is_null($linkNew))
                    {
                        //если такого элемента ещё не было в массиве
                        !isset($array[$_key]) && $array[$_key] = array();
                        //запоминаем ссылку на массив, чтобы к ней после обращаться
                        $linkNew = &$array[$_key];
                    }
                    //а если создана, то дальше крутим
                    else
                    {
                        //если такого элемента ещё не было в массиве
                        !isset($linkNew[$_key]) && $linkNew[$_key] = array();
                        //запоминаем ссылку на массив, чтобы к ней после обращаться
                        $linkNew = &$linkNew[$_key];
                    }
                }
                $linkNew[$lastKey] = $item;
                unset($_key, $linkNew);
                unset($array[$key]);
            }
        }
        unset($item);
    }

    /**
     * Получаем и сливаем результаты выборки, попутно форматируя структуру массива
     * @param CDbDataReader $reader
     * @param bool          $runReformat
     * @return array
     * @author Ivan Chelishchev <chelishchev@gmail.com>
     */
    public function getResults(CDbDataReader $reader, $runReformat = true)
    {
        $result          = array();
        $preRow          = array();
        $preRunSelfMerge = false;
        $constColumns    = array_values($this->_constColumns);
        foreach($reader as $row)
        {
            //форматируем структуру массива
            $runReformat && $this->reformatArray($row);
            //если этот набор является продолжение предыдущего
            if(isset($preRow[$this->_constVar]) && $preRow[$this->_constVar] == $row[$this->_constVar])
            {
                //если не была преобразована строка, чтобы накапливаемые данные сливались в массив — сделаем это
                !$preRunSelfMerge && $this->wrapItemsOfArray($preRow, $constColumns);
                $this->mergeRow($preRow, $row, $constColumns);
                $preRunSelfMerge = true;
                continue;
            }
            //если закончилось накопление в пред.строку, то ее скидываем в результат
            elseif($preRow)
            {
                $result[]        = $preRow;
                $preRunSelfMerge = false;
            }
            $preRow = $row;
        }
        $preRow && $result[] = $preRow;
        unset($preRow, $row, $reader, $preRunSelfMerge);

        return $result;
    }

    /**
     * Обход всех значений и формирование общего отображнеия
     * таблица -> альяс
     * @param array $realToAlias
     * @return array
     */
    protected function rebuildAliasArray(array $realToAlias)
    {
        $new = array();
        foreach($realToAlias as $column => $alias)
        {
            //если эта колонка не имеет установленного нами альяса
            if(!is_string($column))
            {
                //делаем так, так как иначе $column - просто индекс в массиве
                $column = $alias;
            }
            $new[$column] = $alias;
        }
        unset($alias, $column, $realToAlias);

        return $new;
    }

}
