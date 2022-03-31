# Documentation with meaning

Post Knowledge-driven software development arrived.

## License

This work is licensed under the terms of MIT license.

## Major goals

With this tool set and methods we want to achieve the following goals:

1. Increase understandability of software which has a complex business case behind it
2. Increase maintainability of software and related material (like domain knowledge)
3. Decrease number of bugs and related risks when change such software over a long period of time

## Areas

In the following areas knowledge can aid software development.

```
Knowledge <--------> Database
|                       ^
|                       |
`------------------> PHP Classes
```

### Single point of truth

Having a single point of truth means that there is only one source where relevant information about your domain is stored.
In this case we assume our knowledge files form this single point of truth.

Put knowledge-like information in JSON-LD files([compact form](https://www.w3.org/TR/json-ld11-api/#compaction)).
JSON-LD can be read and written by a lot of people and also allows transformation into other RDF serializations.
When storing JSON-LD in files, you can also utilize code versioning control systems (to better track changes and compare states).

### From knowledge to database and back

DWM provides a tool to generate knowledge based on a set of database tables.
The knowledge we talk about here is a set of classes (one for each table) and their relationship to each other.
You don't have to start from scratch, just use this auto generated knowledge as a starting point.

When refining your knowledge you might wanna generate SQL diffs to keep your database up to date.
You can use `bin/generateDBSchemaBasedOnKnowledge` for that (incomplete, maybe buggy and only MySQL is supported for the moment).

Use `bin/generateDBClassesFromKnowledge` to generate PHP classes which represent your RDF classes.
We are working on a way to use these classes to make database operations more type-safe.

### Organize operations using Process pattern

Instead of putting all your code inside of classes (OOP-Style), organize step-wise operations using process pattern.
A process consists of a finite set of steps in a given order and should be used when it represents a straight list of steps to run.
Processes exist in isolation and do not call each other.
Do not use process if you have non-linear code.

### Utilize code versioning

Putting your knowledge into knowledge files allows to include them into your code versioning control system, like GIT.
This way changes can be tracked easily and mechanisms like Githubs's pull requests can be used (to boost collaboration).
Migration operations are easier too, because changes are trackend and can be compared using a diff tool.

### Use static analyzers

Tools like PHPStan help to be as clear as possible about types and classes.
They may find errors beforehand without running the code.
Furthermore, code with explicit type usage is easier to understand in general.

## Common errors

### Jena SHACL

#### java.lang.ClassCastException: class java.lang.String cannot be cast to class java.lang.Integer

This error occour if a value, which is meant to be of type `integer`, is of type `string`.

This is **wrong** (check `sh:minCount` and `sh:maxLength`):

```json
{
    "sh:path": {
        "@id": "dwm:givenName"
    },
    "sh:datatype": {
        "@id": "xsd:string"
    },
    "sh:minCount": "1",
    "sh:maxLength": "255"
}
```

This is **correct**:

```json
{
    "sh:path": {
        "@id": "dwm:givenName"
    },
    "sh:datatype": {
        "@id": "xsd:string"
    },
    "sh:minCount": 1,
    "sh:maxLength": 255
}
```
