# dolibarr-solr
Integrate Dolibarr and SOLR search engine for quick document search

Dolibarr: https://www.dolibarr.org/

Solr: https://lucene.apache.org/solr/

##

This module allows all documents generated and uploaded in Dolibarr to be indexed using remote Solr server.

In that way users can later search full database of documents by different search criteria, including searching file by its contents.

Module can be also used for additional and new modules installed in Dolibarr which uses standard Dolibarr files functionality

In order to search indexed documents ECM module in Dolibarr must be enabled.

## Installation

Module is installed as standard Dolibarr module by downlading and extracting in htdocs folder. 

Name of module folder must be **elbsolr**.

## Configuration

For module working remote Solr server must be installed and configured. 

For information on that need to be checked official Solr documentation 
https://lucene.apache.org/solr/guide/7_0/installing-solr.html

For using advanced indexing function Solr need to be configured to accept additional attributes.

In Solr configuration file **managed-schema** must be added following entry:

```html
<field name="elb_tag" type="text_general" multiValued="true" indexed="true" stored="true"/>
```

## Usage

In admin setup page must be first configured connection to Solr server, url to API and credentials 
if server is protected by username and password.
Currently module supports only basic HTTP authentication.

On status page which is visible only for admins in Dolibarr can be check status of Solr server, 
see number of indexed documents and perform some actions.

- Can be deleted complete index on Solr after confirmation
- Can be reindex all documents in Dolibarr database

For reindexing documents must keep on mind that this is resource and time expensive task, 
which will read all files on documents files system and upload them to Solr server for indexing. 
Because of that action is executed in background and user in any time can see progress of action and abort it.

In order to search through indexed documents ECM module must be enabled in Dolibarr.
Then in Documents module on index page is available link to document search page.

On document search page user can see table list of all indeed files and can perform search by file name, 
content or other additional properties if enabled with other modules.
In case of content search user can see short from file contant if applicable. 
Also link to different modules to which file belongs in displayed in search result.






