# Language Switcher

## Description

This module enables support for multiple languages within a REDCap project. You can switch between any number of configured languages on demand to view different elements in the selected language:
* Data entry forms
* Survey forms
* Additional survey text e.g. instructions, termination message
* All standard page text elements including validation check failure messages

## Mechanism

Create a new project for each additional language you require. Set up this project with any or all of the following, as required:
* Data dictionary containing the variables/forms you require in the language (it does not need to contain all forms of the main project).
* Custom language file on the Edit Project Settings page in the Control Centre containing translations for any standard language elements you wish to view translated (e.g. validation check messages.)

## External Module Configuration

### Primary Language

For the primary language set:

* **Language label**
    Name of primary language as displayed to users in the language switcher select list.

### Additional Languages

Configure the following settings for each language:

* **Language label**
    Name of language as displayed to users in the language switcher select list.
    
* **Project**
    The corresponding project configured with data dictionary and/or language file for the language.

* **DAGs**
    [Optional] Data access groups for which this language will be the default. Applies to both logged-in users assigned to the DAG and survey respondents of records assigned to the DAG.
    
* **Instrument Names**
    [Optional] Alternative instrument names for this language. (Can't be set in the language project because form names would change and form status field names would no longer match the primary project.)    

* **Event Names**
    [Optional] Alternative event names for this language. (Can't be set in the language project because event references required in branching logic would change and no longer match the primary project.)
