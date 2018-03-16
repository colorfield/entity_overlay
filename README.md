# Entity Overlay

Drupal 8 module that loads an entity overlay view mode from another view mode.

Example: on a list of node teasers, a click event on an element of the list displays an overlay of the node full view mode.

## Configuration

Currently for nodes only.

### Node entities list as a Block

- Add a _Nodes overlay_ block.
- Configure the _List view mode_ (e.g. teaser) and the _Overlay view mode_ (e.g. full).

### Field Formatter for entity reference

- Add an entity reference to a node, referencing another node.
- On a view mode, choose the _Overlay rendered entity_ for the formatter.
- Configure then the _List view mode_ and the _Overlay view mode_.

## Roadmap

- Default overlay styling
- Views Formatter
- Change overlay library from the configuration (currently Magnific popup by default)
