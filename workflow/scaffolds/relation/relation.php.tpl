            new Relation(
                '{name}',
                RelationKind::ManyToMany,
                target: $this->{target}With([]),
                key: '{key}',
                pivot: $this->{pivot}(),
                otherKey: '{otherKey}',
            ),
