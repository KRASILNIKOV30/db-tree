CREATE DATABASE tree_of_life;

USE tree_of_life;

DROP TABLE tree_of_life;
DROP TABLE tree_of_life_node;

CREATE TABLE tree_of_life_node
(
  id         INT          NOT NULL,
  name       VARCHAR(200) NOT NULL,
  extinct    BOOLEAN      NOT NULL,
  confidence INT          NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE tree_of_life
(
   node_id INT NOT NULL,
   path    VARCHAR(1024) NOT NULL,
   PRIMARY KEY (node_id),
   CONSTRAINT tol_list_node_id
   FOREIGN KEY (node_id)
   REFERENCES tree_of_life_node (id)
   ON DELETE CASCADE
);
