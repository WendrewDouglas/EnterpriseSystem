SELECT
	A.ID as IDPOSTO,
	A.CNPJ,
	A.RAZAOSOCIAL,
	A.NOMEFANTASIA,
	A.TELEFONE,
	A.CIDADE,
	A.ESTADO,
	'BRASIL' AS PAIS,
	A.STATUS


FROM ATWEB.[atweb_00012].[dbo].[PostosAutorizados] A

	WHERE 1=1;
		--AND STATUS = 'ATIVO'
