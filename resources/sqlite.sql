-- #!sqlite
-- #  { myplot
-- #    { init
-- #      { plotsV2
CREATE TABLE IF NOT EXISTS plotsV2 (level TEXT, X INTEGER, Z INTEGER, name TEXT, owner TEXT, helpers TEXT, denied TEXT, biome TEXT, pvp INTEGER, price FLOAT, PRIMARY KEY (level, X, Z));
-- #      }
-- #      {mergedPlotsV2
CREATE TABLE IF NOT EXISTS mergedPlotsV2 (level TEXT, originX INTEGER, originZ INTEGER, mergedX INTEGER, mergedZ INTEGER, PRIMARY KEY(level, originX, originZ, mergedX, mergedZ));
-- #      }
-- #    }

-- #    { get_plot
-- #        :level string
-- #        :X int
-- #        :Z int
SELECT name, owner, helpers, denied, biome, pvp FROM plotsV2 WHERE level = :level AND X = :X AND Z = :Z;
-- #    }

-- #    { get_plots_by_owner
-- #        :owner string
SELECT * FROM plotsV2 WHERE owner = :owner;
-- #    }

-- #    { get_plots_by_owner_and_level
-- #        :owner string
-- #        :level string
SELECT * FROM plotsV2 WHERE owner = :owner AND level = :level;
-- #    }

-- #    { get_existing_XZ
-- #        :level string
-- #        :number int
SELECT X, Z FROM plotsV2
WHERE (
    level = :level AND
    (
        (abs(X) = :number AND abs(Z) <= :number) OR
        (abs(Z) = :number AND abs(X) <= :number)
    )
);
-- #    }

-- #    { save_plot
-- #        :level string
-- #        :X int
-- #        :Z int
-- #        :name string
-- #        :owner string
-- #        :helpers string
-- #        :denied string
-- #        :biome string
-- #        :pvp int
INSERT OR REPLACE INTO plotsV2 (level, X, Z, name, owner, helpers, denied, biome, pvp) VALUES (:level, :X, :Z, :name, :owner, :helpers, :denied, :biome, :pvp);
-- #    }

-- #    { remove_plot
-- #        :level string
-- #        :X int
-- #        :Z int
DELETE FROM plotsV2 WHERE level = :level AND X = :X AND Z = :Z;
-- #    }
-- #  }