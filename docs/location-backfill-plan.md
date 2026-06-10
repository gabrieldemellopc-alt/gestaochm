# Plano Manual de Backfill por Location

> Documento de planejamento. Nenhuma decisão abaixo foi aplicada ao banco.
>
> Preencha a coluna **Decisão manual** antes da criação das migrations.

## Referência de Locations

| ID | Division | Location | Veículos atuais |
|---:|---|---|---:|
| 1 | AKSA | Barreiras | 19 |
| 2 | AKSA | Luís Eduardo | 1 |
| 3 | AKSA | Imperatriz | 1 |

Não existe atualmente uma location padrão formal no banco.

## Decisão aprovada para backfill inicial

As regras abaixo estão **APROVADAS** para orientar o backfill inicial:

1. Todo dado sem location inferível com segurança será atribuído inicialmente a **Barreiras (location ID 1)**.
2. `tire_entry` 1 será atribuída a **Barreiras (ID 1)**.
3. `tire_entry` 2 será atribuída a **Barreiras (ID 1)**.
4. `tire_entry` 3 será atribuída a **Imperatriz (ID 3)**.
5. Pneus nunca instalados seguirão a location da respectiva `tire_entry`.
6. O pneu descartado com última instalação em Barreiras será atribuído a **Barreiras (ID 1)**.
7. Stock items sem inferência segura serão atribuídos inicialmente a **Barreiras (ID 1)**.
8. Stock movements sem vínculo recuperável seguirão a location aprovada do respectivo `stock_item`.
9. Procedures compartilhados serão clonados por location.
10. O procedure `T21` será atribuído inicialmente a **Barreiras (ID 1)**.
11. Stock categories permanecerão tenant-globais.

Estas decisões definem o destino inicial dos dados; transferências ou redistribuições posteriores deverão ser registradas pelos fluxos funcionais futuros.

## Resumo da Auditoria

- 1 tenant e 1 division (`AKSA`).
- 3 locations: Barreiras, Luís Eduardo e Imperatriz.
- 6 itens de estoque e 49 movimentações.
- 7 procedimentos:
  - 2 vinculados a uma única location;
  - 4 vinculados a múltiplas locations;
  - 1 sem vínculo com veículo.
- 41 pneus:
  - 36 disponíveis;
  - 4 instalados;
  - 1 descartado;
  - nenhum em manutenção.
- 4 pneus instalados possuem location inferível pelo veículo:
  - 1 em Barreiras;
  - 1 em Luís Eduardo;
  - 2 em Imperatriz.
- 36 pneus disponíveis nunca foram instalados e não possuem location inferível.
- 3 entradas de pneus.
- Barreiras concentra a maior parte dos veículos, mas não deve ser considerada location padrão sem aprovação explícita.

## Stock Items

O saldo atual não pode ser dividido automaticamente entre locations. Para itens usados em múltiplas locations, a decisão deve informar como o saldo será distribuído ou em qual location ele ficará inicialmente.

| ID | Nome | Saldo atual | Locations inferidas | Decisão manual de destino | Observação |
|---:|---|---:|---|---|---|
| 1 | Óleo Shell 15w40 | 444,00 L | Barreiras, Luís Eduardo e Imperatriz | **APROVADO: Barreiras (ID 1)** | Compartilhado historicamente; aplicada regra de destino inicial para dado sem inferência única. |
| 2 | FH4401 | 144,00 UNID | Barreiras, Luís Eduardo e Imperatriz | **APROVADO: Barreiras (ID 1)** | Compartilhado historicamente; aplicada regra de destino inicial para dado sem inferência única. |
| 8 | Lubrax1 | 200,00 KG | Nenhuma | **APROVADO: Barreiras (ID 1)** | Sem histórico de uso; aplicada regra oficial. |
| 9 | Aro 40 | 150,00 UNID | Nenhuma | **APROVADO: Barreiras (ID 1)** | Sem histórico de uso; aplicada regra oficial. |
| 10 | Vassoura V10 | 95,00 UNID | Barreiras | **APROVADO: Barreiras (ID 1)** | Evidência de uso somente em Barreiras. |
| 11 | Hidráulico 68 | 105,00 L | Luís Eduardo | **APROVADO: Luís Eduardo (ID 2)** | Evidência de uso em Luís Eduardo; movimento “Foi para LEM” reforça a decisão. |

## Stock Movements Problemáticas

As movimentações abaixo não possuem vínculo seguro com uma manutenção existente. As movimentações de manutenção recuperáveis restantes poderão herdar a location do veículo da manutenção.

| ID | Item | Tipo | Quantidade | Descrição | Inferência possível | Decisão manual |
|---:|---|---|---:|---|---|---|
| 1 | Óleo Shell 15w40 | Saída | 4,00 | Manutenção #3 | Referência de manutenção não encontrada. | **APROVADO: Barreiras (ID 1), seguindo o item** |
| 2 | Óleo Shell 15w40 | Saída | 5,00 | Manutenção #4 | Referência de manutenção não encontrada. | **APROVADO: Barreiras (ID 1), seguindo o item** |
| 3 | Óleo Shell 15w40 | Saída | 1,00 | Manutenção #5 | Referência de manutenção não encontrada. | **APROVADO: Barreiras (ID 1), seguindo o item** |
| 22 | Lubrax1 | Entrada | 200,00 | Estoque inicial | Herda a decisão do item. | **APROVADO: Barreiras (ID 1)** |
| 23 | Aro 40 | Entrada | 150,00 | Estoque inicial | Herda a decisão do item. | **APROVADO: Barreiras (ID 1)** |
| 24 | FH4401 | Entrada | 50,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 25 | FH4401 | Saída | 5,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 26 | FH4401 | Entrada | 55,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 29 | FH4401 | Saída | 250,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 30 | FH4401 | Entrada | 11,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 31 | FH4401 | Entrada | 20,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 32 | Vassoura V10 | Entrada | 100,00 | Estoque inicial | Segue o item. | **APROVADO: Barreiras (ID 1)** |
| 40 | FH4401 | Saída | 3,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 41 | FH4401 | Saída | 20,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 42 | FH4401 | Entrada | 100,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |
| 43 | Hidráulico 68 | Entrada | 100,00 | Estoque inicial | Segue o item. | **APROVADO: Luís Eduardo (ID 2)** |
| 44 | Hidráulico 68 | Saída | 90,00 | Foi para LEM | Forte indicação de Luís Eduardo. | **APROVADO: Luís Eduardo (ID 2)** |
| 45 | Hidráulico 68 | Entrada | 100,00 | Sem descrição | Segue o item. | **APROVADO: Luís Eduardo (ID 2)** |
| 46 | FH4401 | Entrada | 5,00 | Sem descrição | Não inferível; segue o item. | **APROVADO: Barreiras (ID 1)** |

## Procedures

Para procedimentos usados em múltiplas locations, a recomendação é clonar o procedimento e seus campos por location, preservando os registros históricos antes de atualizar vínculos futuros.

| ID | Nome | Locations atuais | Ação recomendada | Decisão manual |
|---:|---|---|---|---|
| 7 | Troca de Óleo | Barreiras, Luís Eduardo e Imperatriz | Clonar por location | **APROVADO: clonar para IDs 1, 2 e 3** |
| 8 | Troca de Vassoura | Barreiras e Imperatriz | Clonar por location | **APROVADO: clonar para IDs 1 e 3** |
| 9 | Lavagem | Barreiras, Luís Eduardo e Imperatriz | Clonar por location | **APROVADO: clonar para IDs 1, 2 e 3** |
| 10 | Troca de Bateria | Barreiras, Luís Eduardo e Imperatriz | Clonar por location | **APROVADO: clonar para IDs 1, 2 e 3** |
| 11 | Teste Procedimento | Barreiras | Atribuir a Barreiras | **APROVADO: Barreiras (ID 1)** |
| 12 | Troca de Óleo Hidráulico | Imperatriz | Atribuir a Imperatriz | **APROVADO: Imperatriz (ID 3)** |
| 13 | T21 | Nenhuma | Atribuir a Barreiras | **APROVADO: Barreiras (ID 1)** |

## Tire Entries

| ID | Prefixo | Quantidade de pneus | Evidência | Location sugerida | Decisão manual |
|---:|---|---:|---|---|---|
| 1 | PN | 1 | O único pneu possui histórico em Barreiras. | Barreiras | **APROVADO: Barreiras (ID 1)** |
| 2 | PN | 20 | Três pneus possuem histórico distribuído entre as três locations; 17 nunca foram instalados. | Barreiras pela regra oficial | **APROVADO: Barreiras (ID 1)** |
| 3 | PN_IMP_ | 20 | Prefixo indica Imperatriz; um pneu possui histórico em Imperatriz e 19 nunca foram instalados. | Imperatriz | **APROVADO: Imperatriz (ID 3)** |

## Tires Sem Location Inferível

Os pneus abaixo não possuem instalação atual ou histórica capaz de determinar location. A sugestão usa somente a entrada, quando existe evidência razoável.

O pneu descartado com última instalação em Barreiras não aparece nesta tabela por possuir inferência histórica; sua atribuição a **Barreiras (ID 1)** está aprovada.

| ID | Código | Status | Entrada | Sugestão | Decisão manual |
|---:|---|---|---:|---|---|
| 5 | PN-0005 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 6 | PN-0006 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 7 | PN-0007 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 8 | PN-0008 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 9 | PN-0009 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 10 | PN-0010 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 11 | PN-0011 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 12 | PN-0012 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 13 | PN-0013 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 14 | PN-0014 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 15 | PN-0015 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 16 | PN-0016 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 17 | PN-0017 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 18 | PN-0018 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 19 | PN-0019 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 20 | PN-0020 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 21 | PN-0021 | Disponível | 2 | Barreiras, seguindo a entrada 2 | **APROVADO: Barreiras (ID 1)** |
| 23 | PN_IMP_-0002 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 24 | PN_IMP_-0003 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 25 | PN_IMP_-0004 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 26 | PN_IMP_-0005 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 27 | PN_IMP_-0006 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 28 | PN_IMP_-0007 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 29 | PN_IMP_-0008 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 30 | PN_IMP_-0009 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 31 | PN_IMP_-0010 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 32 | PN_IMP_-0011 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 33 | PN_IMP_-0012 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 34 | PN_IMP_-0013 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 35 | PN_IMP_-0014 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 36 | PN_IMP_-0015 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 37 | PN_IMP_-0016 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 38 | PN_IMP_-0017 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 39 | PN_IMP_-0018 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 40 | PN_IMP_-0019 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |
| 41 | PN_IMP_-0020 | Disponível | 3 | Imperatriz, seguindo a entrada 3 | **APROVADO: Imperatriz (ID 3)** |

## Próxima Fase Após Aprovação

Após preencher e aprovar este mapa:

1. Transformar as decisões manuais em uma matriz de backfill validável.
2. Definir formalmente se haverá uma location padrão por tenant/division.
3. Criar migrations adicionando `location_id` inicialmente anulável em:
   - `stock_items`;
   - `stock_movements`;
   - `procedures`;
   - `tires`;
   - `tire_entries`.
4. Criar um comando de backfill idempotente, com modo de simulação e relatório de pendências.
5. Executar primeiro em ambiente de teste e comparar totais antes/depois.
6. Aplicar filtros de location nos módulos somente após todos os registros necessários terem `location_id`.
7. Tornar `location_id` obrigatório apenas depois da auditoria final.

## Checklist de Aprovação

- [x] Definir destino inicial para cada `stock_item`.
- [x] Definir location das movimentações problemáticas.
- [x] Aprovar clonagem dos procedures compartilhados.
- [x] Definir location do procedure `T21`.
- [x] Definir location de cada `tire_entry`.
- [x] Aprovar o destino dos pneus sem histórico.
- [x] Aprovar Barreiras (ID 1) como fallback operacional do backfill inicial.
- [x] Manter `stock_categories` tenant-globais.
