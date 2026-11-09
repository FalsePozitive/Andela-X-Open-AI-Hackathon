<?php
declare(strict_types=1);
namespace Passport;

final class Catalog
{
    /** Search only the supplied projection; public callers never supply raw records. */
    public static function search(array $stories, array $query): array
    {
        $read=static fn(string $key):string=>is_string($query[$key]??null) ? mb_substr(trim($query[$key]),0,200) : '';
        $q=$read('q');$media=$read('media');$language=$read('language');$community=$read('community');$status=$read('status');
        $matches=array_values(array_filter($stories,static function(array $s) use($q,$media,$language,$community,$status):bool {
            // Hidden records only match the visible passport ID and steward identity.
            $visible=isset($s['title']);
            if (!$visible && ($media!=='' || $language!=='' || $community!=='')) {return false;}
            if ($media!=='' && ($s['mediaType']??'')!==$media) {return false;}
            if ($language!=='' && mb_strtolower($s['language']??'')!==mb_strtolower($language)) {return false;}
            if ($community!=='' && mb_stripos($s['communityName']??'',$community)===false) {return false;}
            if ($status!=='' && $s['status']!==$status) {return false;}
            $text=$s['id'].' '.$s['creator']['name'].($visible?' '.$s['title'].' '.$s['language'].' '.($s['communityName']??''):'');
            return $q==='' || mb_stripos($text,$q)!==false;
        }));
        $page=is_string($query['page']??null) && ctype_digit($query['page']) ? max(1,min(10000,(int)$query['page'])) : 1;
        return ['stories'=>array_slice($matches,($page-1)*24,24),'total'=>count($matches),'page'=>$page,'filters'=>compact('q','media','language','community','status')];
    }
}
