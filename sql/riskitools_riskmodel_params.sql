CREATE TABLE IF NOT EXISTS riskitools_riskmodel_params (
    rmp_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rmp_model_id BIGINT UNSIGNED NOT NULL,
    rmp_name VARCHAR(255) NOT NULL,
    rmp_expression TEXT NOT NULL,
    rmp_order INT UNSIGNED NOT NULL,
    PRIMARY KEY (rmp_id),
    INDEX rmp_model_id (rmp_model_id),
    UNIQUE INDEX rmp_model_param_name (rmp_model_id, rmp_name),
    FOREIGN KEY (rmp_model_id)
        REFERENCES riskitools_riskmodel(rm_id)
        ON DELETE CASCADE
);
