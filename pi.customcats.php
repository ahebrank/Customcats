<?php
// define the old-style EE object
if (!function_exists('ee')) {
  function ee() {
    static $EE;
    if (! $EE) {
      $EE = get_instance();
    }
    return $EE;
  }
}

$plugin_info = array(
  'pi_name' => 'Customcats',
  'pi_version' =>'0.1',
  'pi_author' =>'Andy Hebrank',
  'pi_author_url' => 'http://insidenewcity.com/',
  'pi_description' => 'Custom category functions',
  'pi_usage' => Customcats::usage()
  );

class Customcats {

  function __construct() {

  }

  /** 
   * Get the first child and all its parents
   *
   * @access public
   * @return string
   */
  function first_child_trail() {
    $groups = ee()->TMPL->fetch_param('show_group', null);
    $entry_id = ee()->TMPL->fetch_param('entry_id', 0);
    $title_field = ee()->TMP->fetch_param('title_field', 'cat_name');
    $sep = ee()->TMP->fetch_param('sep', ' - ');

    $cats = $this->_get_categories($entry_id, $groups);
    $parents = array_map(array($this, '_get_parents'), $cats);

    // now we have an array indexed by category, where each value is a parent trail array
    $cats = array_combine($cats, $parents);

    // order by the parents to find the top trail
    // I think this will work: minimize the order with a cumulative multiplier on each level down
    $rank = array();
    foreach ($cats as $cat_id => $trail) {
      $mult = 1;
      $r = 0;
      foreach ($trail as $parent_cat) {
        $r += $parent_cat['order'];
        $mult *= 100;
      }
      $rank[$cat_id] = $r;
    }

    // get key of min order
    $min_array = array_keys($rank, min($rank));
    $top_cat_id = $min_array[0];

    // now we have a trail
    $trail = array_reverse($cats[$top_cat_id]);
    $trail[] = $top_cat_id;

    // render for display
    $get_titles = function($cat_id) use ($title_field) {
      return $this->_get_title($title_field, $cat_id);
    };
    $title_trail = array_map($get_titles, $trail);

    return implode($sep, $title_trail);
  }

  /** 
   * Get category IDs for a given entry
   *
   * @access private
   * @param int Entry ID
   * @return array
   */
  private function _get_categories($entry_id, $groups = null) {
    ee()->db->select('cp.cat_id')
      ->from('category_posts cp')
      ->where('cp.entry_id', $entry_id);
    if (!is_null($groups)) {
      // filter by group ID
      $groups = explode('|', $groups);
      ee()->db->join('categories c', 'cp.cat_id = c.cat_id')
        ->where_in('c.group_id', $groups);
    }
    $results = ee()->db->get();
    $get_cat_id = function($row) { return $row['cat_id']; };
    return array_map($get_cat_id, $results->result_array());
  }

  /** 
   * Get all the parents for a given category_id
   * returns an array (of arrays) where the last item is the topmost parent
   *
   * @access private
   * @param int Category ID
   * @return array
   */
  private function _get_parents($cat_id) {
    if ($cat_id == 0) return null;
    $trail = array();
    do {
      $results = ee()->db->select('*')
        ->from('categories')
        ->where('cat_id', $cat_id)
        ->get();
      $row = $results->result_array();
      $cat_id = $row['parent_id'];
      $trail[] = array('id' => $cat_id,
                      'order' => $row['cat_order']);
    } while ($cat_id != 0);
    
    return $trail;
  }

  /** 
   * Get the title of a category
   *
   * @access private
   * @param string field name
   * @param int Category ID
   * @return string
   */
  private function _get_title($field, $cat_id) {
    // simple title
    if ($field == 'cat_name') {
      $results = ee()->db->select('*')
        ->from('categories')
        ->where('cat_id', $cat_id)
        ->get();
      $row = $results->result_array();
      return $row['cat_name'];
    }

    // title from custom field
    $results = ee()->db->select('*')
      ->from('category_fields')
      ->where('field_name', $field)
      ->get();
    $row = $results->result_array();
    if (!$row) return null;
    $field_id = $row['field_id'];

    $results = ee()->db->select('*')
      ->from('category_field_data')
      ->where('cat_id', $cat_id)
      ->get();
    $row = $results->result_array();

    return $row['field_id_'.$field_id];
  }

  /** 
   * Usage information
   *
   * @access public
   * @return string
   */
  function usage() {
    ob_start();
?>

<?php
    return ob_get_clean();
  }

}