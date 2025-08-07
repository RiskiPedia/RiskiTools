CREATE TABLE IF NOT EXISTS riskitools_riskmodel (
    rm_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rm_page_id INT UNSIGNED NOT NULL,
    rm_expression TEXT NOT NULL,
    rm_name VARCHAR(255) NOT NULL,
    PRIMARY KEY (rm_id),
    INDEX rm_page_id (rm_page_id),
    UNIQUE INDEX rm_page_name (rm_page_id, rm_name)
);
