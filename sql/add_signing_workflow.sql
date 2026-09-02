USE [LRNPH_OJT];
SET NOCOUNT ON;
SET XACT_ABORT ON;

/*
  Safe, idempotent waiver-signing migration.
  - Works only with dbo.acd_mw_* objects.
  - Creates no foreign keys.
  - Never drops, renames, or alters an existing table.
  - Stops and reports incompatible existing structures for manual review.
*/

IF OBJECT_ID('dbo.acd_mw_signing_tokens', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.acd_mw_signing_tokens (
        token_id INT IDENTITY(1,1) PRIMARY KEY,
        waiver_id INT NOT NULL,
        employee_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        status VARCHAR(20) NOT NULL,
        expires_at DATETIME2 NOT NULL,
        used_at DATETIME2 NULL,
        cancelled_at DATETIME2 NULL,
        created_by INT NOT NULL,
        created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
        verification_user_id INT NULL,
        verification_method VARCHAR(50) NULL,
        verified_at DATETIME2 NULL
    );
END;
ELSE
BEGIN
    DECLARE @MissingColumns NVARCHAR(MAX) = N'';
    DECLARE @ColumnDifferences NVARCHAR(MAX) = N'';
    DECLARE @RequiredColumns TABLE(column_name SYSNAME,type_name SYSNAME,max_length SMALLINT,is_nullable BIT,is_identity BIT);

    INSERT @RequiredColumns(column_name,type_name,max_length,is_nullable,is_identity) VALUES
        ('token_id','int',4,0,1),
        ('waiver_id','int',4,0,0),
        ('employee_id','int',4,0,0),
        ('token_hash','varchar',64,0,0),
        ('status','varchar',20,0,0),
        ('expires_at','datetime2',8,0,0),
        ('used_at','datetime2',8,1,0),
        ('cancelled_at','datetime2',8,1,0),
        ('created_by','int',4,0,0),
        ('created_at','datetime2',8,0,0),
        ('verification_user_id','int',4,1,0),
        ('verification_method','varchar',50,1,0),
        ('verified_at','datetime2',8,1,0);

    SELECT @MissingColumns = STRING_AGG(r.column_name, N', ')
    FROM @RequiredColumns r
    WHERE COL_LENGTH('dbo.acd_mw_signing_tokens', r.column_name) IS NULL;

    IF COALESCE(@MissingColumns, N'') <> N''
    BEGIN
        DECLARE @StructureMessage NVARCHAR(2048) =
            N'Existing dbo.acd_mw_signing_tokens is incompatible. Missing columns: ' + @MissingColumns +
            N'. No automatic ALTER was performed.';
        THROW 51300, @StructureMessage, 1;
    END;

    SELECT @ColumnDifferences = STRING_AGG(r.column_name, N', ')
    FROM @RequiredColumns r
    JOIN sys.columns c ON c.object_id=OBJECT_ID('dbo.acd_mw_signing_tokens','U') AND c.name=r.column_name
    WHERE TYPE_NAME(c.user_type_id)<>r.type_name
       OR c.max_length<>r.max_length
       OR c.is_nullable<>r.is_nullable
       OR c.is_identity<>r.is_identity;

    IF COALESCE(@ColumnDifferences, N'') <> N''
    BEGIN
        DECLARE @TypeMessage NVARCHAR(2048) =
            N'Existing dbo.acd_mw_signing_tokens has incompatible column definitions: ' + @ColumnDifferences +
            N'. No automatic ALTER was performed.';
        THROW 51301, @TypeMessage, 1;
    END;

    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes i
        JOIN sys.index_columns ic ON ic.object_id=i.object_id AND ic.index_id=i.index_id
        JOIN sys.columns c ON c.object_id=ic.object_id AND c.column_id=ic.column_id
        WHERE i.object_id=OBJECT_ID('dbo.acd_mw_signing_tokens','U') AND i.is_primary_key=1 AND c.name='token_id'
    )
        THROW 51302, 'Existing dbo.acd_mw_signing_tokens does not have token_id as its primary key. No automatic ALTER was performed.', 1;

    IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE parent_object_id=OBJECT_ID('dbo.acd_mw_signing_tokens','U'))
        THROW 51303, 'Existing dbo.acd_mw_signing_tokens contains a foreign key. No automatic change was performed.', 1;
END;

IF OBJECT_ID('dbo.acd_mw_settings', 'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM dbo.acd_mw_settings WHERE setting_key = 'APP_BASE_URL')
        INSERT dbo.acd_mw_settings(setting_key, setting_value, updated_at)
        VALUES ('APP_BASE_URL', NULL, SYSDATETIME());

    IF NOT EXISTS (SELECT 1 FROM dbo.acd_mw_settings WHERE setting_key = 'SIGNING_TOKEN_MINUTES')
        INSERT dbo.acd_mw_settings(setting_key, setting_value, updated_at)
        VALUES ('SIGNING_TOKEN_MINUTES', '10', SYSDATETIME());
END;

SELECT 'dbo.acd_mw_signing_tokens' AS object_name,
       'READY' AS migration_status,
       'No foreign keys created; existing project tables were not altered.' AS details;
