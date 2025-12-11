--
-- PostgreSQL database dump
--

\restrict pIgiXwQ7VH785iUggXViZjWh4hc66aamo0VZIw6qktnnXBSYS1P9fNJ54qogECw

-- Dumped from database version 14.20 (Ubuntu 14.20-0ubuntu0.22.04.1)
-- Dumped by pg_dump version 14.20 (Ubuntu 14.20-0ubuntu0.22.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 0
    INCREMENT BY 1
    MINVALUE 0
    NO MAXVALUE
    CACHE 1;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories (
    id integer DEFAULT nextval('public.categories_id_seq'::regclass) NOT NULL,
    name character varying(255) NOT NULL
);


--
-- Name: clients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clients (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    address text,
    phone character varying(20),
    gst_no character varying(255),
    pan character varying(20),
    blocked integer DEFAULT 0 NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: clients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clients_id_seq OWNED BY public.clients.id;


--
-- Name: customer_payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_payments (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: customer_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_payments_id_seq OWNED BY public.customer_payments.id;


--
-- Name: incremental_payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.incremental_payments (
    bid bigint NOT NULL,
    date date NOT NULL,
    amount numeric(12,2) NOT NULL
);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: payment_modes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_modes (
    id bigint NOT NULL,
    name character varying(50) NOT NULL
);


--
-- Name: payment_modes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payment_modes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payment_modes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payment_modes_id_seq OWNED BY public.payment_modes.id;


--
-- Name: pending_registrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pending_registrations (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    mobile character varying(255) NOT NULL,
    country character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    rid integer NOT NULL,
    client_name character varying(255) NOT NULL,
    client_address character varying(255),
    client_phone character varying(255),
    gst_no character varying(255),
    pan character varying(255),
    approved boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: pending_registrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pending_registrations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pending_registrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pending_registrations_id_seq OWNED BY public.pending_registrations.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: product_info; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_info (
    pid bigint NOT NULL,
    hsn_code character varying(255),
    description character varying(500),
    unit_id bigint NOT NULL,
    purchase_price numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    profit_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    pre_gst_sale_cost numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    gst numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    post_gst_sale_cost numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    uid bigint NOT NULL,
    cid bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.products (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    category_id bigint DEFAULT '0'::bigint NOT NULL,
    hscode character varying(255),
    p_unit bigint NOT NULL,
    s_unit bigint DEFAULT '0'::bigint NOT NULL,
    c_factor numeric(22,3) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    cid bigint,
    uid bigint,
    description character varying(500)
);


--
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.products_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- Name: purchase_bills; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_bills (
    id bigint NOT NULL,
    bill_name character varying(255),
    pcid bigint NOT NULL,
    uid bigint NOT NULL,
    payment_mode integer NOT NULL,
    absolute_discount numeric(12,2),
    paid_amount numeric(12,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_bills_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_bills_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_bills_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_bills_id_seq OWNED BY public.purchase_bills.id;


--
-- Name: purchase_clients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_clients (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255),
    phone character varying(20) NOT NULL,
    address text,
    gst_no character varying(255),
    pan character varying(20),
    uid integer NOT NULL,
    cid integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_clients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_clients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_clients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_clients_id_seq OWNED BY public.purchase_clients.id;


--
-- Name: purchase_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_items (
    bid bigint NOT NULL,
    pid bigint NOT NULL,
    p_price numeric(12,2) NOT NULL,
    s_price numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    quantity numeric(22,3) DEFAULT '0'::numeric NOT NULL,
    unit_id bigint NOT NULL,
    dis numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    gst numeric(5,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    role character varying(255) NOT NULL
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sales_bills; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_bills (
    id bigint NOT NULL,
    bill_name character varying(255),
    scid bigint NOT NULL,
    uid bigint NOT NULL,
    payment_mode integer NOT NULL,
    absolute_discount numeric(12,2),
    paid_amount numeric(12,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sales_bills_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_bills_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_bills_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_bills_id_seq OWNED BY public.sales_bills.id;


--
-- Name: sales_clients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_clients (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255),
    phone character varying(20),
    address text,
    gst_no character varying(255),
    pan character varying(20),
    uid integer NOT NULL,
    cid integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sales_clients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_clients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_clients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_clients_id_seq OWNED BY public.sales_clients.id;


--
-- Name: sales_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_items (
    bid bigint NOT NULL,
    pid bigint NOT NULL,
    p_price numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    s_price numeric(12,2) NOT NULL,
    quantity numeric(22,3) DEFAULT '0'::numeric NOT NULL,
    unit_id bigint NOT NULL,
    dis numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    gst numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    serial_numbers text,
    order_index integer DEFAULT 0 NOT NULL
);


--
-- Name: units; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.units (
    id bigint NOT NULL,
    name character varying(50) NOT NULL
);


--
-- Name: units_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.units_id_seq
    START WITH 1
    INCREMENT BY 1
    MINVALUE 0
    NO MAXVALUE
    CACHE 1;


--
-- Name: units_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.units_id_seq OWNED BY public.units.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    mobile character varying(255) NOT NULL,
    country character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    rid integer NOT NULL,
    cid integer NOT NULL,
    blocked integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: clients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clients ALTER COLUMN id SET DEFAULT nextval('public.clients_id_seq'::regclass);


--
-- Name: customer_payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_payments ALTER COLUMN id SET DEFAULT nextval('public.customer_payments_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: payment_modes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_modes ALTER COLUMN id SET DEFAULT nextval('public.payment_modes_id_seq'::regclass);


--
-- Name: pending_registrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pending_registrations ALTER COLUMN id SET DEFAULT nextval('public.pending_registrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: products id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- Name: purchase_bills id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_bills ALTER COLUMN id SET DEFAULT nextval('public.purchase_bills_id_seq'::regclass);


--
-- Name: purchase_clients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_clients ALTER COLUMN id SET DEFAULT nextval('public.purchase_clients_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: sales_bills id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_bills ALTER COLUMN id SET DEFAULT nextval('public.sales_bills_id_seq'::regclass);


--
-- Name: sales_clients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_clients ALTER COLUMN id SET DEFAULT nextval('public.sales_clients_id_seq'::regclass);


--
-- Name: units id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.units ALTER COLUMN id SET DEFAULT nextval('public.units_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: clients clients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_pkey PRIMARY KEY (id);


--
-- Name: customer_payments customer_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_payments
    ADD CONSTRAINT customer_payments_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: payment_modes payment_modes_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_modes
    ADD CONSTRAINT payment_modes_name_unique UNIQUE (name);


--
-- Name: payment_modes payment_modes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_modes
    ADD CONSTRAINT payment_modes_pkey PRIMARY KEY (id);


--
-- Name: pending_registrations pending_registrations_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pending_registrations
    ADD CONSTRAINT pending_registrations_email_unique UNIQUE (email);


--
-- Name: pending_registrations pending_registrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pending_registrations
    ADD CONSTRAINT pending_registrations_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: purchase_bills purchase_bills_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_bills
    ADD CONSTRAINT purchase_bills_pkey PRIMARY KEY (id);


--
-- Name: purchase_clients purchase_clients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_clients
    ADD CONSTRAINT purchase_clients_pkey PRIMARY KEY (id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_role_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_role_unique UNIQUE (role);


--
-- Name: sales_bills sales_bills_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_bills
    ADD CONSTRAINT sales_bills_pkey PRIMARY KEY (id);


--
-- Name: sales_clients sales_clients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_clients
    ADD CONSTRAINT sales_clients_pkey PRIMARY KEY (id);


--
-- Name: units units_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.units
    ADD CONSTRAINT units_pkey PRIMARY KEY (id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: sales_items_bid_order_index_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_items_bid_order_index_index ON public.sales_items USING btree (bid, order_index);


--
-- Name: incremental_payments incremental_payments_bid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.incremental_payments
    ADD CONSTRAINT incremental_payments_bid_foreign FOREIGN KEY (bid) REFERENCES public.sales_bills(id) ON DELETE CASCADE;


--
-- Name: purchase_items purchase_items_bid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_items
    ADD CONSTRAINT purchase_items_bid_foreign FOREIGN KEY (bid) REFERENCES public.purchase_bills(id) ON DELETE CASCADE;


--
-- Name: sales_items sales_items_bid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_items
    ADD CONSTRAINT sales_items_bid_foreign FOREIGN KEY (bid) REFERENCES public.sales_bills(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict pIgiXwQ7VH785iUggXViZjWh4hc66aamo0VZIw6qktnnXBSYS1P9fNJ54qogECw

--
-- PostgreSQL database dump
--

\restrict VU04309rS6s84cco8mkTdvl5a5S9GhbfBPCBchX1QyrCU9XAPcTU4odgfvQPsog

-- Dumped from database version 14.20 (Ubuntu 14.20-0ubuntu0.22.04.1)
-- Dumped by pg_dump version 14.20 (Ubuntu 14.20-0ubuntu0.22.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2014_04_04_045933_create_categories_table	1
2	2014_10_12_000000_create_users_table	1
3	2019_12_14_000001_create_personal_access_tokens_table	1
4	2025_02_20_054026_create_roles_table	1
5	2025_02_24_000000_create_units_table	1
6	2025_02_24_093916_create_products_table	1
7	2025_06_30_045007_create_clients_table	1
8	2025_06_30_094254_create_purchase_clients_table	1
9	2025_07_02_102905_create_purchase_bills_table	1
10	2025_07_02_104916_create_purchase_items_table	1
11	2025_07_03_043827_create_payment_modes_table	1
12	2025_07_04_122059_create_sales_clients_table	1
13	2025_07_05_105735_create_sales_bills_table	1
14	2025_07_05_110050_create_sales_items_table	1
15	2025_08_04_052804_create_product_info_table	1
16	2025_09_17_061818_add_cid_and_uid_to_products_table	1
17	2025_09_24_045550_create_pending_registrations_table	2
18	2025_09_25_112201_add_description_to_products_table	3
19	2025_10_14_022040_create_incremental_payments_table	4
20	2025_12_08_105734_add_serial_numbers_to_sales_items_table	5
21	2025_12_09_044115_add_order_index_to_sales_items_table	6
22	2025_12_11_063646_create_customer_payments_table	7
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 22, true);


--
-- PostgreSQL database dump complete
--

\unrestrict VU04309rS6s84cco8mkTdvl5a5S9GhbfBPCBchX1QyrCU9XAPcTU4odgfvQPsog

