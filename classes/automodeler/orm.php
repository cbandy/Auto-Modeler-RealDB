<?php
/**
* AutoModelerORM
*
* @package        AutoModeler
* @author         Jeremy Bush
* @copyright      (c) 2010 Jeremy Bush
* @license        http://www.opensource.org/licenses/isc-license.txt
*/

class AutoModeler_ORM extends AutoModeler
{
	protected $_has_many = array();
	protected $_belongs_to = array();

	/**
	 * Magic get method, can obtain one to many relationships
	 *
	 * @param String $key the key to __get
	 *
	 * @return mixed
	 */
	public function __get($key)
	{
		// See if we are requesting a foreign key
		if (isset($this->_data[$key.'_id']))
			// Get the row from the foreign table
			return $this->_db->select(array('*'))->from(inflector::plural($key))->where('id', '=', $this->_data[$key.'_id'])->as_object('Model_'.inflector::singular(ucwords($key)))->execute($this->_db)->current();
		else if (isset($this->_data[$key]))
			return $this->_data[$key];
	}

	/**
	 * Magic set method, can set many to many relationships
	 *
	 * @param string $key the key to set
	 * @param mixed  $value the value to set the key to
	 *
	 * @return none
	 */
	public function __set($key, $value)
	{
		if (in_array($key, $this->_has_many))
		{
			$this_key = inflector::singular($this->_table_name).'_id';
			$f_key = inflector::singular($key).'_id';

			// See if this is already in the join table
			// TODO this could be select(new Database_Expression('COUNT(*)'))
			if ( ! count($this->_db->select(array('*'))->from($this->_table_name.'_'.$key)->where($f_key, '=', $value)->where($this_key, '=', $this->_data['id'])->execute($this->_db)))
			{
				// Insert
				$this->_db->insert($this->_table_name.'_'.$key)->columns(array($f_key, $this_key))->values(array($value, $this->_data['id']))->execute($this->_db);
			}
		}
		else if (in_array($key, $this->_belongs_to))
		{
			$this_key = inflector::singular($this->_table_name).'_id';
			$f_key = inflector::singular($key).'_id';
			// See if this is already in the join table
			// TODO this could be select(new Database_Expression('COUNT(*)'))
			if ( ! count($this->_db->select(array('*'))->from($key.'_'.$this->_table_name)->where($this_key, '=', $value)->where($f_key, '=', $this->_data['id'])->execute($this->_db)))
			{
				// Insert
				$this->_db->insert($key.'_'.$this->_table_name)->columns(array($f_key, $this_key))->values(array($value, $this->_data['id']))->execute($this->_db);
			}
		}
		else
			parent::__set($key, $value);
	}

	/**
	 * Performs mass relations
	 *
	 * @param string $key    the key to set
	 * @param array  $values an array of values to relate the model with
	 *
	 * @return none
	 */
	public function relate($key, array $values)
	{
		if (in_array($key, $this->_has_many))
		{
			$this_key = inflector::singular($this->_table_name).'_id';
			$f_key = inflector::singular($key).'_id';
			foreach ($values as $value)
				// See if this is already in the join table
				// TODO this could be select(new Database_Expression('COUNT(*)'))
				if ( ! count($this->_db->select(array('*'))->from($this->_table_name.'_'.$key)->where($f_key, '=', $value)->where($this_key, '=', $this->_data['id'])->execute($this->_db)))
				{
					// Insert
					$this->_db->insert($this->_table_name.'_'.$key)->columns(array($f_key, $this_key))->values(array($value, $this->_data['id']))->execute($this->_db);
				}
		}
	}

	/**
	 * Finds many to many relationships
	 *
	 * @param string $key      the model name to look for
	 * @param mixed  $where    search conditions
	 * @param array  $order_by the column to order by
	 * @param array  $order    the direction to order
	 *
	 * @return Database_Result
	 */
	public function find_related($key, $where = NULL, $order_by = 'id', $order = 'ASC')
	{
		$model = 'Model_'.inflector::singular($key);

		$temp = new $model();
		if (isset($temp->{inflector::singular($this->_table_name).'_id'})) // Look for a one to many relationship
		{
			$query = $this->_db->select(array('*'))->from($key)->order_by($order_by, $order)->where(inflector::singular($this->_table_name).'_id', '=', $this->_data['id']);
		}
		else // Get a many to many relationship
		{
			$join_table = $this->_table_name.'_'.$key;
			$this_key = inflector::singular($this->_table_name).'_id';
			$f_key = inflector::singular($key).'_id';

			$query = $this->_db->select(array($key.'.*'))->from($this->_db->from($key)->join($join_table)->on($join_table.'.'.$f_key, '=', $key.'.id'))->order_by($order_by, $order)->where($join_table.'.'.$this_key, '=', $this->_data['id']);
		}

		if ($where)
		{
			$query->parameters[':where']->and_open();

			if (is_array($where))
			{
				foreach ($where as $cond)
					$query->parameters[':where']->and_column($cond[0], $cond[1], $cond[2]);
			}
			else
			{
				$query->parameters[':where']->add('and', $where);
			}

			$query->parameters[':where']->close();
		}

		return $query->as_object('Model_'.inflector::singular(ucwords($key)))->execute($this->_db);
	}

	/**
	 * Finds parents of a belongs_to model
	 *
	 * @param string $key      the model name to look for
	 * @param mixed  $where    search conditions
	 * @param array  $order_by the column to order by
	 * @param array  $order    the direction to order
	 *
	 * @return Database_Result
	 */
	public function find_parent($key, $where = NULL, $order_by = 'id', $order = 'ASC')
	{
		if (isset($this->{$key.'_id'})) // Look for a one to many relationship
		{
			$query = $this->_db->select(array('*'))->from(inflector::plural($this->_table_name))->where('id', '=', $this->_data[$key.'_id']);

			if ($where)
			{
				$query->parameters[':where']->and_open();

				if (is_array($where))
				{
					foreach ($where as $cond)
						$query->parameters[':where']->and_column($cond[0], $cond[1], $cond[2]);
				}
				else
				{
					$query->parameters[':where']->add('and', $where);
				}

				$query->parameters[':where']->close();
			}

			return $query->as_object('Model_'.ucwords($this->_table_name))->execute($this->_db);
		}
		else
		{
			$join_table = $key.'_'.$this->_table_name;
			$f_key = inflector::singular($this->_table_name).'_id';
			$this_key = inflector::singular($key).'_id';

			$query = $this->_db->select(array($key.'.*'))->from($this->_db->from($key)->join($join_table, $join_table.'.'.$this_key, $key.'.id'))->order_by($order_by, $order)->where($join_table.'.'.$f_key, '=', $this->_data['id']);

			if ($where)
			{
				$query->parameters[':where']->and_open();

				if (is_array($where))
				{
					foreach ($where as $cond)
						$query->parameters[':where']->and_column($cond[0], $cond[1], $cond[2]);
				}
				else
				{
					$query->parameters[':where']->add('and', $where);
				}

				$query->parameters[':where']->close();
			}

			return $query->as_object('Model_'.inflector::singular(ucwords($key)))->execute($this->_db);
		}
	}

	/**
	 * Tests if a many to many relationship exists
	 *
	 * @param string $key   the model name to look for
	 * @param string $value an id to search for
	 *
	 * @return bool
	 */
	public function has($key, $value)
	{
		$join_table = $this->_table_name.'_'.$key;
		$f_key = inflector::singular($key).'_id';
		$this_key = inflector::singular($this->_table_name).'_id';

		if (in_array($key, $this->_has_many))
		{
			// TODO this could be select(new Database_Expression('COUNT(?)', array(new Database_Column($key.'.id')))
			return (bool) $this->_db->select(array($key.'.id'))->from($this->_db->from($key)->join($join_table)->on($join_table.'.'.$f_key, '=', $key.'.id'))
				->where($this->_db->conditions()->and_column($join_table.'.'.$this_key, '=', $this->_data['id'])->and_column($join_table.'.'.$f_key, '=', $value))
				->execute($this->_db)->count();
		}
		return FALSE;
	}

	/**
	 * Removes a relationship if you aren't using innoDB (shame on you!)
	 *
	 * @param string $key the model name to look for
	 * @param string $id  an id to search for
	 *
	 * @return integer
	 */
	public function remove($key, $id)
	{
		return $this->_db->delete($this->_table_name.'_'.inflector::plural($key))
			->where($this->_db->conditions()->and_column($key.'_id', '=', $id)->and_column(inflector::singular($this->_table_name).'_id', '=', $this->_data['id']))
			->execute($this->_db);
	}

	/**
	 * Removes all relationships of a model
	 *
	 * @param string $key the model name to look for
	 *
	 * @return integer
	 */
	public function remove_all($key)
	{
		if (in_array($key, $this->_has_many))
		{
			return $this->_db->delete($this->_table_name.'_'.$key)->where(inflector::singular($this->_table_name).'_id', '=', $this->id)->execute($this->_db);
		}
		else if (in_array($key, $this->_belongs_to))
		{
			return $this->_db->delete($key.'_'.$this->_table_name)->where(inflector::singular($this->_table_name).'_id', '=', $this->id)->execute($this->_db);
		}
	}

	/**
	 * Removes a parent relationship of a belongs_to
	 *
	 * @param string $key the model name to look for
	 *
	 * @return integer
	 */
	public function remove_parent($key)
	{
		return $this->_db->delete($key.'_'.$this->_table_name)->where(inflector::singular($this->_table_name).'_id', '=', $this->id)->execute($this->_db);
	}
}
