<?php
/**
 * Website: http://sourceforge.net/projects/simplehtmldom/
 * A simple PHP HTML DOM parser.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! function_exists( 'str_get_html' ) ) {

    define( 'HDOM_TYPE_ELEMENT', 1 );
    define( 'HDOM_TYPE_COMMENT', 2 );
    define( 'HDOM_TYPE_TEXT', 3 );
    define( 'HDOM_TYPE_ENDTAG', 4 );
    define( 'HDOM_TYPE_ROOT', 5 );

    define( 'HDOM_QUOTE_DOUBLE', 0 );
    define( 'HDOM_QUOTE_SINGLE', 1 );
    define( 'HDOM_QUOTE_NO', 3 );

    define( 'HDOM_INFO_BEGIN', 0 );
    define( 'HDOM_INFO_END', 1 );
    define( 'HDOM_INFO_QUOTE', 2 );
    define( 'HDOM_INFO_SPACE', 3 );
    define( 'HDOM_INFO_TEXT', 4 );
    define( 'HDOM_INFO_INNER', 5 );
    define( 'HDOM_INFO_OUTER', 6 );

    class simple_html_dom_node {
        public $nodetype = HDOM_TYPE_ELEMENT;
        public $tag = 'root';
        public $attr = array();
        public $children = array();
        public $nodes = array();
        public $parent = null;
        public $_ = array();
        public $tag_start = 0;
        private $dom = null;

        public function __construct( $dom ) {
            $this->dom = $dom;
            $dom->nodes[] = $this;
        }

        public function __destruct() {
            $this->clear();
        }

        public function __toString() {
            return $this->outertext();
        }

        public function clear() {
            $this->dom = null;
            $this->nodes = array();
            $this->children = array();
            $this->parent = null;
        }

        public function outertext() {
            if ( isset( $this->_[HDOM_INFO_OUTER] ) ) {
                return $this->_[HDOM_INFO_OUTER];
            }

            if ( isset( $this->_[HDOM_INFO_TEXT] ) ) {
                return $this->dom->restore_noise( $this->_[HDOM_INFO_TEXT] );
            }

            $ret = '';
            if ( isset( $this->_[HDOM_INFO_BEGIN] ) ) {
                if ( ! empty( $this->attr ) ) {
                    $attr_str = '';
                    foreach ( $this->attr as $k => $v ) {
                        if ( $v === true ) {
                            $attr_str .= ' ' . $k;
                        } else {
                            $attr_str .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"';
                        }
                    }
                    $is_self_closing = ( substr( trim( $this->_[HDOM_INFO_BEGIN] ), -2 ) === '/>' );
                    $ret = '<' . $this->tag . $attr_str . ( $is_self_closing ? ' />' : '>' );
                } else {
                    $ret = $this->dom->restore_noise( $this->_[HDOM_INFO_BEGIN] );
                }
            }

            if ( isset( $this->_[HDOM_INFO_INNER] ) ) {
                if ( $this->tag !== 'script' && $this->tag !== 'style' ) {
                    $ret .= $this->dom->restore_noise( $this->_[HDOM_INFO_INNER] );
                } else {
                    $ret .= $this->_[HDOM_INFO_INNER];
                }
            } else {
                foreach ( $this->nodes as $c ) {
                    $ret .= $c->outertext();
                }
            }

            if ( isset( $this->_[HDOM_INFO_END] ) ) {
                $ret .= $this->_[HDOM_INFO_END];
            }

            return $ret;
        }

        public function innertext() {
            if ( isset( $this->_[HDOM_INFO_INNER] ) ) {
                return $this->_[HDOM_INFO_INNER];
            }

            if ( isset( $this->_[HDOM_INFO_TEXT] ) ) {
                return $this->dom->restore_noise( $this->_[HDOM_INFO_TEXT] );
            }

            $ret = '';
            foreach ( $this->nodes as $c ) {
                $ret .= $c->outertext();
            }

            return $ret;
        }

        public function __set( $name, $value ) {
            switch ( $name ) {
                case 'outertext':
                    return $this->_[HDOM_INFO_OUTER] = $value;
                case 'innertext':
                    if ( isset( $this->_[HDOM_INFO_INNER] ) ) {
                        return $this->_[HDOM_INFO_INNER] = $value;
                    }
                    if ( isset( $this->_[HDOM_INFO_TEXT] ) ) {
                        return $this->_[HDOM_INFO_TEXT] = $value;
                    }
                    $this->_[HDOM_INFO_INNER] = $value;
                    return $value;
            }

            if ( ! isset( $this->attr[$name] ) || $this->attr[$name] !== $value ) {
                $this->attr[$name] = $value;
            }
        }

        public function __get( $name ) {
            if ( isset( $this->attr[$name] ) ) {
                return $this->attr[$name];
            }
            switch ( $name ) {
                case 'outertext':
                    return $this->outertext();
                case 'innertext':
                    return $this->innertext();
                default:
                    return null;
            }
        }

        public function __isset( $name ) {
            switch ( $name ) {
                case 'outertext':
                case 'innertext':
                    return true;
            }
            return isset( $this->attr[$name] );
        }

        public function hasAttribute( $name ) {
            return isset( $this->attr[$name] );
        }

        public function getAttribute( $name ) {
            return $this->__get( $name );
        }

        public function setAttribute( $name, $value ) {
            $this->__set( $name, $value );
        }

        public function removeAttribute( $name ) {
            unset( $this->attr[$name] );
        }

        public function find( $selector, $idx = null, $lowercase = false ) {
            $selectors = $this->parse_selector( $selector );
            if ( empty( $selectors ) ) return array();

            $count = count( $selectors );
            $ret = array();

            for ( $c = 0; $c < $count; ++$c ) {
                if ( empty( $selectors[$c] ) ) continue;

                $target = array( $this );
                for ( $i = 0; $i < count( $selectors[$c] ); ++$i ) {
                    $list = array();
                    foreach ( $target as $node ) {
                        $node->search( $selectors[$c][$i], $list, $lowercase );
                    }
                    $target = $list;
                }

                foreach ( $target as $node ) {
                    $ret[spl_object_hash( $node )] = $node;
                }
            }

            $ret = array_values( $ret );
            if ( is_null( $idx ) ) return $ret;
            if ( $idx < 0 ) $idx = count( $ret ) + $idx;

            return isset( $ret[$idx] ) ? $ret[$idx] : null;
        }

        protected function search( $selector, &$ret, $lowercase = false ) {
            $tag    = $selector['tag'];
            $key    = $selector['key'];
            $val    = $selector['val'];
            $exp    = $selector['exp'];
            $no_key = $selector['no_key'];

            if ( $this->nodetype !== HDOM_TYPE_ELEMENT && $this->nodetype !== HDOM_TYPE_ROOT ) {
                return;
            }

            // Check element matching
            $pass = true;
            if ( $tag !== '' && $tag !== '*' && strtolower( $tag ) !== strtolower( $this->tag ) ) {
                $pass = false;
            }

            if ( $pass && ! $no_key ) {
                if ( $key === 'id' ) {
                    $attr_val = isset( $this->attr['id'] ) ? $this->attr['id'] : '';
                    if ( $val !== $attr_val ) $pass = false;
                } elseif ( $key === 'class' ) {
                    $attr_val = isset( $this->attr['class'] ) ? $this->attr['class'] : '';
                    $classes = preg_split( '/\s+/', trim( $attr_val ) );
                    if ( ! in_array( $val, $classes, true ) ) $pass = false;
                } else {
                    $attr_val = isset( $this->attr[$key] ) ? $this->attr[$key] : null;
                    if ( is_null( $attr_val ) ) {
                        $pass = false;
                    } elseif ( $val !== '' && $attr_val !== $val ) {
                        $pass = false;
                    }
                }
            }

            if ( $pass && $this->nodetype === HDOM_TYPE_ELEMENT ) {
                $ret[] = $this;
            }

            foreach ( $this->children as $child ) {
                $child->search( $selector, $ret, $lowercase );
            }
        }

        protected function parse_selector( $selector_string ) {
            $pattern = '/([\w\-:\*]*)(?:\#([\w\-]+)|(?:\[@?([\w\-]+)(?:([!*^$|~]?=)["\']?(.*?)["\']?)?\])|(?:\.([\w\-]+)))*/';

            $selectors = array();
            $parts = explode( ',', $selector_string );

            foreach ( $parts as $part ) {
                $part = trim( $part );
                if ( empty( $part ) ) continue;

                $sub_parts = preg_split( '/\s*(?:>|\s)\s*/', $part );
                $sub_selectors = array();

                foreach ( $sub_parts as $sub ) {
                    $sub = trim( $sub );
                    if ( empty( $sub ) ) continue;

                    $tag = '*';
                    $key = '';
                    $val = '';
                    $exp = '=';
                    $no_key = true;

                    if ( strpos( $sub, '#' ) === 0 ) {
                        $key = 'id';
                        $val = substr( $sub, 1 );
                        $no_key = false;
                    } elseif ( strpos( $sub, '.' ) === 0 ) {
                        $key = 'class';
                        $val = substr( $sub, 1 );
                        $no_key = false;
                    } else {
                        if ( preg_match( '/^([\w\-]+)?(?:\#([\w\-]+))?(?:\.([\w\-]+))?$/', $sub, $m ) ) {
                            $tag = ! empty( $m[1] ) ? $m[1] : '*';
                            if ( ! empty( $m[2] ) ) {
                                $key = 'id';
                                $val = $m[2];
                                $no_key = false;
                            } elseif ( ! empty( $m[3] ) ) {
                                $key = 'class';
                                $val = $m[3];
                                $no_key = false;
                            }
                        } else {
                            $tag = $sub;
                        }
                    }

                    $sub_selectors[] = array(
                        'tag'    => $tag,
                        'key'    => $key,
                        'val'    => $val,
                        'exp'    => $exp,
                        'no_key' => $no_key,
                    );
                }

                if ( ! empty( $sub_selectors ) ) {
                    $selectors[] = $sub_selectors;
                }
            }

            return $selectors;
        }
    }

    class simple_html_dom {
        public $root = null;
        public $nodes = array();
        public $char = '';
        public $size = 0;
        public $pos = 0;
        public $doc = '';
        public $noise = array();

        public function __construct( $str = null ) {
            if ( $str ) {
                $this->load( $str );
            }
        }

        public function __destruct() {
            $this->clear();
        }

        public function load( $str ) {
            $this->clear();
            $this->doc = $str;
            $this->size = strlen( $str );

            $this->root = new simple_html_dom_node( $this );
            $this->root->tag = 'root';
            $this->root->nodetype = HDOM_TYPE_ROOT;

            $this->parse();
        }

        public function clear() {
            foreach ( $this->nodes as $node ) {
                $node->clear();
            }
            $this->nodes = array();
            $this->root = null;
        }

        public function save() {
            return $this->root->outertext();
        }

        public function find( $selector, $idx = null ) {
            return $this->root->find( $selector, $idx );
        }

        public function restore_noise( $text ) {
            while ( ( $pos = strpos( $text, '___noise___' ) ) !== false ) {
                $end = strpos( $text, '___', $pos + 11 );
                if ( $end === false ) break;

                $key = substr( $text, $pos, $end - $pos + 3 );
                if ( isset( $this->noise[$key] ) ) {
                    $text = substr_replace( $text, $this->noise[$key], $pos, $end - $pos + 3 );
                } else {
                    break;
                }
            }
            return $text;
        }

        protected function parse() {
            $dom = $this;
            $doc = $this->doc;

            // Protect script and style tags from breaking parser
            $doc = preg_replace_callback( '#<(script|style)\b[^>]*?>.*?</\1>#is', function( $matches ) use ( $dom ) {
                $key = '___noise___' . count( $dom->noise ) . '___';
                $dom->noise[$key] = $matches[0];
                return $key;
            }, $doc );

            $pattern = '#(<([a-z0-9_:-]+)(\s+[^>]*?)?/?>)|(</([a-z0-9_:-]+)\s*>)#i';

            $stack = array( $this->root );
            $last_pos = 0;

            preg_match_all( $pattern, $doc, $matches, PREG_OFFSET_CAPTURE );

            if ( empty( $matches[0] ) ) {
                $text_node = new simple_html_dom_node( $this );
                $text_node->nodetype = HDOM_TYPE_TEXT;
                $text_node->_[HDOM_INFO_TEXT] = $doc;
                $this->root->nodes[] = $text_node;
                return;
            }

            foreach ( $matches[0] as $i => $match ) {
                $match_str = $match[0];
                $match_pos = $match[1];

                // Text before match
                if ( $match_pos > $last_pos ) {
                    $text = substr( $doc, $last_pos, $match_pos - $last_pos );
                    $text_node = new simple_html_dom_node( $this );
                    $text_node->nodetype = HDOM_TYPE_TEXT;
                    $text_node->_[HDOM_INFO_TEXT] = $text;
                    $parent = end( $stack );
                    $parent->nodes[] = $text_node;
                }

                $is_close = ! empty( $matches[4][$i][0] );

                if ( $is_close ) {
                    $tag_name = strtolower( $matches[5][$i][0] );
                    // Pop stack until matching tag is found
                    for ( $c = count( $stack ) - 1; $c > 0; --$c ) {
                        if ( strtolower( $stack[$c]->tag ) === $tag_name ) {
                            $stack[$c]->_[HDOM_INFO_END] = $match_str;
                            array_splice( $stack, $c );
                            break;
                        }
                    }
                } else {
                    $tag_name   = strtolower( $matches[2][$i][0] );
                    $attr_str   = isset( $matches[3][$i][0] ) ? $matches[3][$i][0] : '';
                    $is_self_close = ( substr( trim( $match_str ), -2 ) === '/>' ) || in_array( $tag_name, array( 'img', 'br', 'hr', 'input', 'meta', 'link', 'source' ), true );

                    $node = new simple_html_dom_node( $this );
                    $node->tag = $tag_name;
                    $node->nodetype = HDOM_TYPE_ELEMENT;
                    $node->_[HDOM_INFO_BEGIN] = $match_str;

                    // Parse attributes
                    if ( ! empty( $attr_str ) ) {
                        preg_match_all( '#([a-z0-9_:-]+)(?:\s*=\s*(?:(["\'])(.*?)\2|([^\s>]+)))?#i', $attr_str, $attr_matches, PREG_SET_ORDER );
                        foreach ( $attr_matches as $am ) {
                            $attr_name = strtolower( $am[1] );
                            $attr_val  = isset( $am[3] ) && $am[3] !== '' ? $am[3] : ( isset( $am[4] ) ? $am[4] : true );
                            $node->attr[$attr_name] = $attr_val;
                        }
                    }

                    $parent = end( $stack );
                    $node->parent = $parent;
                    $parent->children[] = $node;
                    $parent->nodes[] = $node;

                    if ( ! $is_self_close ) {
                        $stack[] = $node;
                    }
                }

                $last_pos = $match_pos + strlen( $match_str );
            }

            // Remaining text
            if ( $last_pos < strlen( $doc ) ) {
                $text = substr( $doc, $last_pos );
                $text_node = new simple_html_dom_node( $this );
                $text_node->nodetype = HDOM_TYPE_TEXT;
                $text_node->_[HDOM_INFO_TEXT] = $text;
                $parent = end( $stack );
                $parent->nodes[] = $text_node;
            }
        }
    }

    function str_get_html( $str ) {
        if ( empty( $str ) ) return false;
        $dom = new simple_html_dom();
        $dom->load( $str );
        return $dom;
    }
}
