# Documentation with meaning (DWM)

DWM is a project which explores real knowledge-driven approaches to bolster software development.
Focus is currently on systems written with PHP 8.0+.

**Current state:** Under heavy development, pre-alpha.

## License

This work is licensed under the terms of MIT license.

## Why using DWM?

DWM is intended to be used for software which has a complex business case behind it and (heavily) relies on domain knowledge.
Domain knowledge consists of all information which describe the domain of interest, like important terms, class hierarchies etc.
Currently domain knowledge is either put into the source code and/or relational database model.

This has the following **disadvantages**:
* Knowledge stored in source code means that in case of domain changes you are forced to update your code too.
* Besides domain knowledge, code usually contains platform (e.g. operating system) and vendor-component related information and routines. You will end with a mixture of different things.
* Changes in domain knowledge may lead to code changes, which could result in bugs. Same for changes ín platform or vendor-component related code: They might lead to bugs and faulty behavior.
* When testing the knowledge-related part of your code you have to make separate it first.

### How does source code with domain knowledge look like?

#### Example 1

Source code which contains domain knowledge may look like:

```php
/*
 * domain is e-commerce
 */

$price = 100.0;

/**
 * VAT = Valued Added Tax
 *
 * @var int
 */
$vat = 19;

if (19 != $vat) {
    // error, VAT must be 19%!
} else {
    // VAT correct, do something
}
```

In this example we deploy the VAT (Valued Added Tax) concept to our data.
VAT is usually a float `0 =<` and is used to compute a brutto price.

What happens when our VAT changes from `19` to `18.1`?

#### Example 2

Another example is code like:

```php
class Person {
   public ?string $name = null;
}

$person1 = new Person();
$person1->name = 'Name';

// ...

// we check that name is not empty
if (null != $person1->name && 0 < strlen($person1->name)) {
    // Person::name is correct
} else {
    // Error, name is empty or null
}
```

In this example we build a structure which enforces a certain data model constraint.
The constraint is, that `name` of `Person` must not be empty.

What happens when `name` can be null and might exist multiple times?

## Major goals

DWM aims to provide tools and methods to achieve the following goals:

1. Increase **understandability** of source code which has a complex business case behind it
2. Increase **maintainability** of source code and related material (like domain knowledge)
3. Decrease **number of bugs** and related risks when change source code over a long period of time
4. Increase **productivity** by providing tools to ease major knowledge-related tasks.

## Areas

In the following areas knowledge can aid software development.

```
Software <---------> PHP Classes
   |                    ^
   |                    |
   |    ,-------------->|
   |   /                |
   v  /                 |
Domain                  v
Knowledge <--------> Database
```

### Single point of truth

Having a single point of truth means that there is only one source where relevant information about your domain is stored.
In this case we assume our knowledge files form this single point of truth.

Put knowledge-like information in JSON-LD files ([compact form](https://www.w3.org/TR/json-ld11-api/#compaction)).
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

### Use static analyzers

Tools like PHPStan help to be as clear as possible about types and classes.
They may find errors beforehand without running the code.
Furthermore, code with explicit type usage is easier to understand in general.

### Utilize code versioning

Putting your knowledge into knowledge files allows to include them into your code versioning control system, like GIT.
This way changes can be tracked easily and mechanisms like Githubs's pull requests can be used (to boost collaboration).
Migration operations are easier too, because changes are trackend and can be compared using a diff tool.

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
