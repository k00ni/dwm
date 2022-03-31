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

### Put knowledge-like information in JSON-LD files

Use JSON-LD to model your knowledge ([compact form](https://www.w3.org/TR/json-ld11-api/#compaction)).

### Organize operations in processes

Instead of putting the majority of your code inside a set of classes (OOP-Style), organize it as processes (more like imperative programming).
A process consists of a finite set of steps in a given order.
Processes exist in isolation and do not call each other.

### Use static analyzers

Tools like PHPStan help to be as clear as possible about types and classes.
They may find errors beforehand without running the code.
Furthermore, code with explicit type usage can easier to understand.

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
