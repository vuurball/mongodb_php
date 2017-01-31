<?php

//connect > mongo
$m = new MongoClient("mongodb://10.0.8.201:27017");
//var_dump($m);
//use test db > use test 
$db = $m->test;

//use grades collection 
$grades_coll = $db->grades;

mFindOne($grades_coll);
mFind($grades_coll);
mFindByStudentId($grades_coll);
aggregateByClassAndStudent($grades_coll);
collectionDropAndCreateAndInsert($db);
indexes($db);
echo $grades_coll->getName();
distinctAndCountAndBatchInsert($db);
group($db);








/**
 * db.grades.aggregate([
 * {
 *     $match: { 
 *        student_id: 4
 *     }
 * },
 * {
 *    "$unwind": "$scores"
 * },
 * {
 *     $group: {
 *            _id: { student_id:'$student_id', class_id:'$class_id'},
 *            scores_count : { $sum: 1}, //count()
 *            scores_avg : { $avg: "$scores.score"} //avg()
 *           
 *     }
 * }])
 * show count and avg of scores for each student (optional in each class)
 */
function aggregateByClassAndStudent($collection)
{
    $ops = array(
        array(
            '$match' => array(
                "student_id" => 4/* , "class_id" => 12 */
            )
        ),
        array('$unwind' => '$scores'), //go down one embedded level becase grouping on the embedded docs
        array(
            '$group' => array(
                "_id" => array('student_id' => '$student_id', 'class_id' => '$class_id'),
                "scores_count" => array('$sum' => 1),
                "scores_avg" => array('$avg' => '$scores.score'),
            ),
        ),
    );
    try {
        $results = $collection->aggregate($ops);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    var_dump($results);
}

/**
* batch inserting docs to collection
* fetching distict docs
* fetching counts
*/
function distinctAndCountAndBatchInsert($db)
{
    $people = $db->people;
    $people->drop();

    $peopleArr = [
        array("name" => "Joe", "points" => 22),
        array("name" => "Molly", "points" => 43),
        array("name" => "Sally", "points" => 22),
        array("name" => "Joe", "points" => 87),
        array("name" => "Dan", "points" => 87)
    ];

    $people->batchInsert($peopleArr);

    var_dump($people->distinct('name'));
    echo '<hr>';
    var_dump($people->distinct('points'));
    echo '<hr>';
    var_dump($people->count());
    echo '<hr>';
    var_dump($people->count(["name" => "Joe"]));
}

/**
 * group by points
 * retuns total rows, total distict after grouping
 * returns array of docs related to the group by key (names)
 * @param type $db
 */
function group($db)
{
    $people = $db->people;
    $people->drop();

    $peopleArr = [
        array("name" => "Joe", "points" => 22),
        array("name" => "Molly", "points" => 43),
        array("name" => "Sally", "points" => 22),
        array("name" => "Joe", "points" => 87),
        array("name" => "Dan", "points" => 87)
    ];

    $people->batchInsert($peopleArr);

    $key = ['points' => 1];
    $initial = ['items' => []];
    $reduce = "function(obj, prev){prev.items.push(obj.name);}";
    $result = $people->group($key, $initial, $reduce);
    var_dump($result);
}

/**
* creating, getting and deleting indexes
*/
function indexes($db)
{
    $people = $db->people;
    $people->drop();

    // Insert some sample data 
    $people->insert(array("name" => "Joe", "points" => 4));
    $people->insert(array("name" => "Molly", "points" => 43));
    $people->insert(array("name" => "Sally", "points" => 22));
    $people->insert(array("name" => "John", "points" => 87));

    $people->createIndex(['name' => 1], ['unique' => true, 'name' => 'index1']);
    $people->createIndex(['points' => 1], ['name' => 'index2']);

    try {
        $people->insert(array("name" => "Joe", "points" => 22)); //not going to be inserted
    } catch (Exception $e) {
        // echo $e->getMessage(); // E11000 duplicate key error index: test.people.$index1 dup key: { : "Joe" }
    }
    $people->createIndex(['name' => 1, 'points' => -1], ['name' => 'index3']);
    var_dump($people->getIndexInfo()); //show all indexes

    $people->deleteIndex(['name' => 'index1']);
    $people->deleteIndexes(); //remove the rest
}

/**
 * drops a collection
 * creates new collection by inserting new documents to it
 */
function collectionDropAndCreateAndInsert($db)
{
    $people = $db->people;
    $people->drop();

    // Insert some sample data 
    $people->insert(array("name" => "Joe", "points" => 4));
    $people->insert(array("name" => "Molly", "points" => 43));
    $people->insert(array("name" => "Sally", "points" => 22));
    $people->insert(array("name" => "Joe", "points" => 22));
    $people->insert(array("name" => "Molly", "points" => 87));
    $people->insert(array("name" => "remove me", "points" => 1));
    $people->insert(array("name" => "remove me", "points" => 2));
    $people->insert(array("name" => "modify me", "points" => 2));

    //add lastname to one of the Joe's docs (can only update 1 doc at a time)
    $people->update(array("name" => "Joe"), array('$set' => array("lastname" => "Smith")));

    //change Sally's name to Jason
    $people->update(array('name' => 'Sally'), array('$set' => array('name' => 'Jason')));

    $people->findAndModify(["name" => "modify me"], ['$set' => ['msg' => 'i was modified!!']]);
    //add address to an existing doc
    $doc = $people->findOne();
    $doc['address'] = ['st' => 'Privet drive', 'apt' => 4];
    $people->save($doc);

    //remove 1 doc 
    $people->remove(['name' => 'remove me'], ['justOne' => true]);

    $results = $people->find();
    // Run the command cursor 
    foreach ($results as $document)
    {
        var_dump($document);
    }
}

/**
 * db.getCollection('grades').find({student_id:4})
 * @param type $collection
 */
function mFindByStudentId($collection)
{
    echo "<hr>";
    $where = array(
        'student_id' => 45
    );
    $cursor = $collection->find($where);
    //iterate through the results
    foreach ($cursor as $document)
    {
        var_dump($document);
    }
}

/**
 * find all docs and print out 10 first
 * db.getCollection('grades').find()
 * @param type $collection
 */
function mFind($collection)
{
    echo "<hr>";
    $counter = 0;
    $cursor = $collection->find();
    //iterate through the results
    foreach ($cursor as $document)
    {
        $counter++;
        echo "Student ID: " . $document['student_id'] . " - class_id: " . $document['class_id'] . "<br>";
        if (count($document['scores']) > 0)
        {
            echo "SCORES:<br>";
            foreach ($document['scores'] as $scores)
            {
                echo "TYPE:" . $scores['type'] . " | SCORE:" . $scores['score'] . "<br>";
            }
        }
        if ($counter == 10)
        {
            break;
        }
    }
}

/**
 * find & print one document  
 * db.getCollection('grades').findOne()
 * @param type $collection
 */
function mFindOne($collection)
{
    echo "<hr>";
    $document = $collection->findOne();

    //iterate through the results
    if (count($document) > 0)
    {

        echo "Student ID: " . $document['student_id'] . "<br>";
        if (count($document['scores']) > 0)
        {
            echo "SCORES:<br>";
            foreach ($document['scores'] as $scores)
            {
                echo "TYPE:" . $scores['type'] . " | SCORE:" . $scores['score'] . "<br>";
            }
        }
    }
}

/**
 * done                 aggregate
 * doesnt work          aggregateCursor
 * done                 batchInsert
 * done                 count
 * doesnt work          createDBRef
 * done                 distinct
 * done                 drop
 * done                 find
 * done                 findAndModify
 * done                 findOne
 * doesnt work          getDBRef
 * done                 createIndex
 * done                 deleteIndexes
 * done                 deleteIndex
 * done                 getIndexInfo
 * done                 getName
 * done                 group
 * done                 insert
 * done                 remove
 * done                 save
 * done                 update
 * not sure what for    validate
 */
