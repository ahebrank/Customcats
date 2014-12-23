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
   * this is useful in the particular case where you want a breadcrumb-type trail of category titles
   * but only want the first child branch (by category order) to show up
   *
   * multilingual support through specification of a custom field to use instead of the category name
   *
   * @access public
   * @return string
   */
  function first_child_trail() {
    $groups = ee()->TMPL->fetch_param('show_group', null);
    $entry_id = ee()->TMPL->fetch_param('entry_id', 0);
    $title_field = ee()->TMPL->fetch_param('title_field', 'cat_name');
    $sep = ee()->TMPL->fetch_param('sep', ' - ');

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
        $r += $mult * $parent_cat['order'];
        $mult *= 100;
      }

      // down-rank if category is top-level
      if (count($trail) == 1) $r += 1e6;

      $rank[$cat_id] = $r;
    }

    // get key of min order
    $min_array = array_keys($rank, min($rank));
    $top_cat_id = $min_array[0];

    // now we can make a title trail
    $trail = array_reverse($cats[$top_cat_id]);

    // render for display
    $get_titles = function($cat) use ($title_field) {
      return $this->_get_title($title_field, $cat['id']);
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

    // stick the initial child at the top
    $results = ee()->db->select('*')
      ->from('categories')
      ->where('cat_id', $cat_id)
      ->get();
    $row = $results->result_array();

    $trail = array();
    $trail[] = array('id' => $cat_id,
                    'order' => $row[0]['cat_order']);
    $parent_id = $row['0']['parent_id'];

    while ($parent_id != 0) {
      $results = ee()->db->select('*')
        ->from('categories')
        ->where('cat_id', $parent_id)
        ->get();
      $row = $results->result_array();

      $trail[] = array('id' => $parent_id,
                      'order' => $row[0]['cat_order']);

      $parent_id = $row[0]['parent_id'];
    }

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
  private function _get_title($field = 'cat_name', $cat_id) {
    // try title from custom field?
    if ($field != 'cat_name') {
      $results = ee()->db->select('*')
        ->from('category_fields')
        ->where('field_name', $field)
        ->get();
      $field_row = $results->result_array();
      if (!$field_row) $field = 'cat_name';
    }

    // simple title
    if ($field == 'cat_name') {
      $results = ee()->db->select('*')
        ->from('categories')
        ->where('cat_id', $cat_id)
        ->get();
      $row = $results->result_array();
      if (!$row) return null;
      return $row[0]['cat_name'];
    }

    // title from custom field
    $field_id = $field_row[0]['field_id'];
    $results = ee()->db->select('*')
      ->from('category_field_data')
      ->where('cat_id', $cat_id)
      ->get();
    $row = $results->result_array();

    return $row[0]['field_id_'.$field_id];
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