<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VocabularyJmdict;

class DictionaryController extends Controller
{
    public function search(Request $request) {
        $start = microtime(true);
        
        $dictionary = $request->dictionary;
        $term = $request->term;


        $ids = [];
        // exact word matches
        $search = VocabularyJmdict::select('id')->whereRelation('words', 'word', 'like', $term)->get()->toArray();
        foreach ($search as $result) {
            if (!in_array($result, $ids, true)) {
                array_push($ids, $result);
            }
        }

        // exact reading matches
        $search = VocabularyJmdict::select('id')->whereRelation('readings', 'reading', 'like', $term)->get()->toArray();
        foreach ($search as $result) {
            if (!in_array($result, $ids, true)) {
                array_push($ids, $result);
            }
        }

        // partial word matches, max 10
        $search = VocabularyJmdict::select('id')->whereRelation('words', 'word', 'like', $term . '%')->get()->toArray();
        foreach ($search as $result) {
            if (!in_array($result, $ids, true) && count($ids) < 10) {
                array_push($ids, $result);
            }
        }

        // partial reading matches, max 10
        $search = VocabularyJmdict::select('id')->whereRelation('readings', 'reading', 'like', $term . '%')->get()->toArray();
        foreach ($search as $result) {
            if (!in_array($result, $ids, true) && count($ids) < 10) {
                array_push($ids, $result);
            }
        }

        $search = VocabularyJmdict::with('words:word,id,dictionary_ja_jmdict_id')->with('readings:reading,word_restrictions,id,dictionary_ja_jmdict_id')->whereIn('id', $ids)->get();
        
        $translations = [];
        foreach ($search as $result) {
            $translation = new \StdClass();
            $translation->words = [];
            $translation->definitions = [];
            $translation->conjugations = $result->conjugations == '' ? [] : json_decode($result->conjugations);

            $dictionaryDefinitions = json_decode($result->translations);
            foreach ($dictionaryDefinitions as $definition) {
                if (count($definition->restrictions)) {
                    array_push($translation->definitions, '(' . implode(', ', $definition->restrictions) . ') ' . $definition->definition);
                } else {
                    array_push($translation->definitions, $definition->definition);
                }
            }

            // make each word form a result
            foreach ($result->words as $word) {
                // get all possible readings for each word forms
                foreach ($result->readings as $reading) {
                    array_push($translation->words, $word->word . ' (' . $reading->reading . ')');
                }
            }

            array_push($translations, $translation);
        }

        return json_encode($translations);
    }
}