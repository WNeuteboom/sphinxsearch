<?php 
namespace WNeuteboom\SphinxSearch;

class SphinxSearch
{
    protected $connection;
    protected $total_found;
    protected $time;

    protected $term;
    protected $index;
    protected $limit = 100;
    protected $offset = 0;

    protected $table;
    protected $with = array();

    public function __construct($args = array())
    {
        $this->connection = new \Sphinx\SphinxClient();
        $this->connection->setServer($args['host'], $args['port']);
        $this->connection->setConnectTimeout($args['timeout']);
    }

    public function reset()
    {
        $this->resetFilters();
        $this->resetGroupBy();
        $this->resetOverrides();

        $this->setFieldWeights(array());

        return $this;
    }

    public function select()
    {
        $this->SetSelect(implode(",", func_get_args()));

        return $this;
    }

    public function index($index)
    {
        $this->index = $index;

        return $this;
    }

    public function search($term)
    {
        $this->term = $term;

        return $this;
    }

    public function table($table)
    {
        $this->table = $table;

        return $this;
    }

    public function with($with = array())
    {
        $this->with = $with;

        return $this;
    }

    public function where($field, $value, $exclude = false) 
    {
        if (empty($value)) {
            return $this;
        }

        if (is_array($value))
        {
            $val = array();

            foreach ($value as $v)
                $val[] = (int)$v;
        }
        else
        {
            $val = array((int)$value);
        }

        $this->SetFilter($field, $val, $exclude);

        return $this;
    }

    public function whereNot($field, $value)
    {
        $this->where($field, $value, true);

        return $this;
    }

    public function whereFloatRange($field, $from, $to)
    {
        $this->SetFilterFloatRange($field, (float)$from, (float)$to);

        return $this;
    }

    public function whereRange($field, $from, $to)
    {
        $this->SetFilterRange($field, $from, $to);

        return $this;
    }

    public function skip($value) 
    {
        $this->offset = $value;
        $this->SetLimits($this->offset, $this->limit);

        return $this;
    }

    public function take($value)
    {
        $this->limit = $value;
        $this->SetLimits($this->offset, $this->limit);

        return $this;
    }

    public function sortmode($sortmode)
    {
        $this->orderBy("", $sortmode);

        return $this;
    }

    public function orderBy($field, $sortmode)
    {
        if(gettype($sortmode) === "string")
        {
            $sortmode = strtoupper($sortmode);
            $sortmode = str_replace("SPH_SORT_", "", $sortmode);

            switch($sortmode) 
            {
                case "RELEVANCE":       $sortmode = \Sphinx\SphinxClient::SPH_SORT_RELEVANCE;       break;
                case "ATTR_DESC":       $sortmode = \Sphinx\SphinxClient::SPH_SORT_ATTR_DESC;       break;
                case "ATTR_ASC":        $sortmode = \Sphinx\SphinxClient::SPH_SORT_ATTR_ASC;        break;
                case "TIME_SEGMENTS":   $sortmode = \Sphinx\SphinxClient::SPH_SORT_TIME_SEGMENTS;   break;
                case "EXTENDED":        $sortmode = \Sphinx\SphinxClient::SPH_SORT_EXTENDED;        break;
                case "EXPR":            $sortmode = \Sphinx\SphinxClient::SPH_SORT_EXPR;            break;
            }
        }

        $this->SetSortMode($sortmode, $field);

        return $this;
    }

    public function rankmode($rankmode) 
    {
        if(gettype($rankmode) === "string")
        {
            $rankmode = strtoupper($rankmode);
            $rankmode = str_replace("SPH_RANK_", "", $rankmode);

            switch($rankmode)
            {
                case "PROXIMITY_BM25":  $rankmode = \Sphinx\SphinxClient::SPH_RANK_PROXIMITY_BM25;   break;
                case "BM25":            $rankmode = \Sphinx\SphinxClient::SPH_RANK_BM25;             break;
                case "NONE":            $rankmode = \Sphinx\SphinxClient::SPH_RANK_NONE;             break;
                case "WORDCOUNT":       $rankmode = \Sphinx\SphinxClient::SPH_RANK_WORDCOUNT;        break;
                case "PROXIMITY":       $rankmode = \Sphinx\SphinxClient::SPH_RANK_PROXIMITY;        break;
                case "MATCHANY":        $rankmode = \Sphinx\SphinxClient::SPH_RANK_MATCHANY;         break;
                case "FIELDMASK":       $rankmode = \Sphinx\SphinxClient::SPH_RANK_FIELDMASK;        break;
                case "SPH04":           $rankmode = \Sphinx\SphinxClient::SPH_RANK_SPH04;            break;
                case "EXPR":            $rankmode = \Sphinx\SphinxClient::SPH_RANK_EXPR;             break;
                case "TOTAL":           $rankmode = \Sphinx\SphinxClient::SPH_RANK_TOTAL;            break;
            }
        }

        $this->setRankingMode($rankmode);

        return $this;
    }

    public function matchmode($matchmode) 
    {
        if(gettype($matchmode) === "string")
        {
            $matchmode = strtoupper($matchmode);
            $matchmode = str_replace("SPH_MATCH_", "", $matchmode);

            switch($matchmode) 
            {
                case "ALL":         $matchmode = \Sphinx\SphinxClient::SPH_MATCH_ALL;       break;
                case "ANY":         $matchmode = \Sphinx\SphinxClient::SPH_MATCH_ANY;       break;
                case "PHRASE":      $matchmode = \Sphinx\SphinxClient::SPH_MATCH_PHRASE;    break;
                case "BOOLEAN":     $matchmode = \Sphinx\SphinxClient::SPH_MATCH_BOOLEAN;   break;
                case "EXTENDED":    $matchmode = \Sphinx\SphinxClient::SPH_MATCH_EXTENDED;  break;
                case "FULLSCAN":    $matchmode = \Sphinx\SphinxClient::SPH_MATCH_FULLSCAN;  break;
                case "EXTENDED2":   $matchmode = \Sphinx\SphinxClient::SPH_MATCH_EXTENDED2; break;
            }
        }

        $this->setMatchMode($matchmode);

        return $this;
    }

    public function weights($value = array()) 
    {
        $this->SetFieldWeights($value);

        return $this;
    }

    public function groupBy($field, $groupby) 
    {
        if(gettype($groupby) === "string")
        {
            $groupby = strtoupper($groupby);
            $groupby = str_replace("SPH_GROUPBY_", "", $groupby);

            switch($groupby) 
            {
                case "DAY":         $groupby = \Sphinx\SphinxClient::SPH_GROUPBY_DAY;      break;
                case "WEEK":        $groupby = \Sphinx\SphinxClient::SPH_GROUPBY_WEEK;     break;
                case "MONTH":       $groupby = \Sphinx\SphinxClient::SPH_GROUPBY_MONTH;    break;
                case "YEAR":        $groupby = \Sphinx\SphinxClient::SPH_GROUPBY_YEAR;     break;
                case "ATTR":        $groupby = \Sphinx\SphinxClient::SPH_GROUPBY_ATTR;     break;
                case "ATTRPAIR":    $groupby = \Sphinx\SphinxClient::SPH_GROUPBY_ATTRPAIR; break;
            }
        }

        $this->setGroupBy($field, $groupby);

        return $this;
    }

    public function add()
    {
        return $this->AddQuery($this->term, $this->index);
    }

    private function processResult($result)
    {
        if ($result)
        {
            $this->total_found  = $result['total_found'];
            $this->time         = $result['time'];

            if ($result['total'] && isset($result['matches']))
            {
                if (!empty($this->table))
                {
                    $match_ids = array_keys($result['matches']);

                    if ($this->table instanceof \Illuminate\Database\Eloquent\Model)
                    {
                        $result = $this->table->whereIn("id", $match_ids)
                                              ->with($this->with)
                                              ->orderByRaw(\DB::raw("FIELD(id, " . implode(",", $match_ids) . ")"))
                                              ->get();

                        return $result;
                    }
                    else if ($this->table)
                    {
                        $result = \DB::table($this->table)
                                        ->whereIn("id", $match_ids)
                                        ->orderByRaw(\DB::raw("FIELD(id, " . implode(",", $match_ids) . ")"))
                                        ->get();   

                        return $result;
                    }
                }

                return $result['matches'];
            }
        }

        return false;
    }

    public function run()
    {
        $this->total_found  = 0;
        $this->time         = 0;

        $results = $this->RunQueries();

        if (empty($results)) 
            return false;

        $processed_results = array();

        foreach($results as $index => $result)
        {
            $processed_results[$index] = $this->processResult($result);
        }

        return $processed_results;
    }

    public function get() 
    {
        $this->total_found  = 0;
        $this->time         = 0;

        $result = $this->query($this->term, $this->index);

        if (is_array($result)) {
            return $this->processResult($result);
        }

        return false;
    }

    public function total_found()
    {
        return $this->total_found;
    }

    public function time()
    {
        return $this->time;
    }

    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->connection, $method), $parameters);
    }
}